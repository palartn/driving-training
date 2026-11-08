<?php

namespace Tests\Feature;

use App\Models\TrainingSession;
use App\Services\AttendanceService;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\Support\TrainingSessionFixtures;
use Tests\TestCase;

class SessionRulesTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;
    use TrainingSessionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @return array{0: \App\Models\Center, 1: \App\Models\User, 2: \App\Models\Subject, 3: \App\Models\Course, 4: \App\Models\Instructor} */
    private function scenario(): array
    {
        $center = $this->makeCenter();
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('قوانين السير');
        $course = $this->makeCourse($center, [$subject]);
        $instructor = $this->makeInstructor([$center]);
        $this->assignInstructor($course, $subject, $instructor);

        return [$center, $head, $subject, $course, $instructor];
    }

    public function test_a_department_head_creates_a_session(): void
    {
        [, $head, $subject, $course, $instructor] = $this->scenario();

        $session = $this->makeSession($course, $subject, $head, $instructor);

        $this->assertSame('scheduled', $session->status->value);
        $this->assertSame(90, (int) $session->fresh()->duration_minutes);
        $this->assertSame($instructor->id, $session->instructor_id);
    }

    public function test_an_employee_cannot_create_sessions(): void
    {
        [$center, , $subject, $course] = $this->scenario();
        $employee = $this->makeUser('employee', $center);

        $this->assertBusinessRule(
            fn () => $this->makeSession($course, $subject, $employee),
            'ليست لديك صلاحية',
        );
    }

    public function test_the_instructor_must_be_assigned_to_the_subject_in_the_course(): void
    {
        [$center, $head, $subject, $course] = $this->scenario();
        $unassigned = $this->makeInstructor([$center], 'غير مسند');

        $this->assertBusinessRule(
            fn () => $this->makeSession($course, $subject, $head, $unassigned),
            'إلى هذه المادة',
        );
    }

    public function test_the_instructor_must_teach_at_the_course_center(): void
    {
        [, $head, $subject, $course] = $this->scenario();
        $elsewhere = $this->makeInstructor([$this->makeCenter('B')], 'مدرب مركز آخر');
        $this->assignInstructor($course, $subject, $elsewhere);

        $this->assertBusinessRule(
            fn () => $this->makeSession($course, $subject, $head, $elsewhere),
            'لا يدرّس',
        );
    }

    public function test_an_instructor_cannot_be_double_booked_even_across_centers(): void
    {
        $centerA = $this->makeCenter('A');
        $centerB = $this->makeCenter('B');
        $gm = $this->makeUser('general_manager');
        $subjectA = $this->makeSubject('أ');
        $subjectB = $this->makeSubject('ب');
        $courseA = $this->makeCourse($centerA, [$subjectA]);
        $courseB = $this->makeCourse($centerB, [$subjectB]);
        $shared = $this->makeInstructor([$centerA, $centerB], 'مدرب مشترك');
        $this->assignInstructor($courseA, $subjectA, $shared);
        $this->assignInstructor($courseB, $subjectB, $shared);

        $this->makeSession($courseA, $subjectA, $gm, $shared, ['starts_at' => '09:00', 'ends_at' => '10:30']);

        $this->assertBusinessRule(
            fn () => $this->makeSession($courseB, $subjectB, $gm, $shared, ['starts_at' => '10:00', 'ends_at' => '11:00']),
            'تتقاطع',
        );

        // التلامس عند الحدّ (تنتهي 10:30 وتبدأ 10:30) ليس تقاطعًا.
        $adjacent = $this->makeSession($courseB, $subjectB, $gm, $shared, ['starts_at' => '10:30', 'ends_at' => '11:30']);

        $this->assertNotNull($adjacent->id);
    }

    public function test_the_session_date_must_fall_within_the_course_period(): void
    {
        [, $head, $subject, $course] = $this->scenario();

        $this->assertBusinessRule(
            fn () => $this->makeSession($course, $subject, $head, null, ['session_date' => today()->addDays(60)->toDateString()]),
            'خارج فترة الدورة',
        );
    }

    public function test_sessions_cannot_be_scheduled_for_a_finished_course(): void
    {
        $center = $this->makeCenter();
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('أ');
        $course = $this->makeCourse($center, [$subject], 'finished');

        $this->assertBusinessRule(
            fn () => $this->makeSession($course, $subject, $head),
            'لا تُجدول جلسات',
        );
    }

    public function test_generate_creates_weekly_sessions(): void
    {
        [, $head, $subject, $course, $instructor] = $this->scenario();

        $created = app(SessionService::class)->generate(
            $this->sessionData($course, $subject, $instructor) + [
                'weekdays' => [today()->dayOfWeek],
                'from' => today()->toDateString(),
                'count' => 3,
            ],
            $head,
        );

        $this->assertCount(3, $created);
        $this->assertSame(
            [today()->toDateString(), today()->addDays(7)->toDateString(), today()->addDays(14)->toDateString()],
            $created->map(fn (TrainingSession $s) => $s->session_date->toDateString())->all(),
        );
    }

    public function test_generate_is_all_or_nothing(): void
    {
        [, $head, $subject, $course, $instructor] = $this->scenario();

        // الدورة 30 يومًا، وست جلسات أسبوعية تتجاوزها.
        $this->assertBusinessRule(
            fn () => app(SessionService::class)->generate(
                $this->sessionData($course, $subject, $instructor) + [
                    'weekdays' => [today()->dayOfWeek],
                    'from' => today()->toDateString(),
                    'count' => 6,
                ],
                $head,
            ),
            'خارج فترة الدورة',
        );

        $this->assertSame(0, TrainingSession::count());
    }

    public function test_a_session_with_attendance_cannot_be_cancelled(): void
    {
        [$center, $head, $subject, $course] = $this->scenario();
        $employee = $this->makeUser('employee', $center);
        $enrollment = $this->enroll($this->makeStudent($center), $course, $employee);
        $session = $this->makeSession($course, $subject, $head);

        app(AttendanceService::class)->record($session, [$enrollment->id => ['status' => 'present']], $employee);

        $this->assertBusinessRule(
            fn () => app(SessionService::class)->update(
                $session->fresh(),
                $this->sessionData($course, $subject) + ['status' => 'cancelled'],
                $head,
            ),
            'لا تُلغى',
        );
    }
}
