<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Course;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\Support\TrainingSessionFixtures;
use Tests\TestCase;

class AttendanceRulesTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;
    use TrainingSessionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @return array{0: \App\Models\Center, 1: \App\Models\User, 2: \App\Models\User, 3: \App\Models\Subject, 4: \App\Models\Course, 5: \App\Models\Enrollment, 6: \App\Models\TrainingSession} */
    private function scenario(): array
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('قوانين السير');
        $course = $this->makeCourse($center, [$subject]);
        $enrollment = $this->enroll($this->makeStudent($center), $course, $employee);
        $session = $this->makeSession($course, $subject, $head);

        return [$center, $employee, $head, $subject, $course, $enrollment, $session];
    }

    private function service(): AttendanceService
    {
        return app(AttendanceService::class);
    }

    public function test_recording_attendance_marks_the_session_as_held(): void
    {
        [, $employee, , , , $enrollment, $session] = $this->scenario();

        $result = $this->service()->record($session, [$enrollment->id => ['status' => 'present']], $employee);

        $this->assertSame(1, $result['changed']);
        $this->assertSame(['present' => 1], $result['summary']);
        $this->assertSame(SessionStatus::Held, $session->fresh()->status);
        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_attendance_cannot_be_recorded_before_the_session_day(): void
    {
        [$center, $employee, $head, $subject, $course, $enrollment] = $this->scenario();
        $future = $this->makeSession($course, $subject, $head, null, ['session_date' => today()->addDays(3)->toDateString()]);

        $this->assertBusinessRule(
            fn () => $this->service()->record($future, [$enrollment->id => ['status' => 'present']], $employee),
            'قبل يوم الجلسة',
        );
    }

    public function test_attendance_cannot_be_recorded_for_a_cancelled_session(): void
    {
        [, $employee, , , , $enrollment, $session] = $this->scenario();
        $session->update(['status' => 'cancelled']);

        $this->assertBusinessRule(
            fn () => $this->service()->record($session->fresh(), [$enrollment->id => ['status' => 'present']], $employee),
            'ملغاة',
        );
    }

    public function test_a_student_of_another_course_is_rejected(): void
    {
        [$center, $employee, , $subject, , , $session] = $this->scenario();
        $otherCourse = $this->makeCourse($center, [$subject]);
        $stranger = $this->enroll($this->makeStudent($center), $otherCourse, $employee);

        $this->assertBusinessRule(
            fn () => $this->service()->record($session, [$stranger->id => ['status' => 'present']], $employee),
            'لا يتبع دورة',
        );
    }

    public function test_a_new_record_is_rejected_for_a_non_active_enrollment(): void
    {
        [, $employee, $head, , , $enrollment, $session] = $this->scenario();

        app(EnrollmentService::class)->close($enrollment, EnrollmentStatus::Withdrawn, 'انسحاب', $head);

        $this->assertBusinessRule(
            fn () => $this->service()->record($session, [$enrollment->id => ['status' => 'present']], $employee),
            'ليس جاريًا',
        );
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        [, $employee, , , , $enrollment, $session] = $this->scenario();

        $this->assertBusinessRule(
            fn () => $this->service()->record($session, [$enrollment->id => ['status' => 'maybe']], $employee),
            'حالة حضور غير صالحة',
        );
    }

    public function test_updating_an_existing_record_counts_as_a_change_and_repeating_it_does_not(): void
    {
        [, $employee, , , , $enrollment, $session] = $this->scenario();

        $this->service()->record($session, [$enrollment->id => ['status' => 'present']], $employee);

        $second = $this->service()->record($session->fresh(), [$enrollment->id => ['status' => 'absent', 'notes' => 'تأخر']], $employee);
        $third = $this->service()->record($session->fresh(), [$enrollment->id => ['status' => 'absent', 'notes' => 'تأخر']], $employee);

        $this->assertSame(1, $second['changed']);
        $this->assertSame(0, $third['changed']);
        $this->assertSame('absent', AttendanceRecord::first()->status->value);
    }

    public function test_a_closed_course_needs_a_department_head_to_change_attendance(): void
    {
        [, $employee, $head, , $course, $enrollment, $session] = $this->scenario();

        $this->service()->record($session, [$enrollment->id => ['status' => 'present']], $employee);
        Course::whereKey($course->id)->update(['status' => 'finished']);

        $this->assertBusinessRule(
            fn () => $this->service()->record($session->fresh(), [$enrollment->id => ['status' => 'absent']], $employee),
            'الدورة منتهية',
        );

        $result = $this->service()->record($session->fresh(), [$enrollment->id => ['status' => 'absent']], $head);

        $this->assertSame(1, $result['changed']);
    }

    public function test_recording_attendance_is_audited(): void
    {
        [, $employee, , , , $enrollment, $session] = $this->scenario();

        $this->service()->record($session, [$enrollment->id => ['status' => 'present']], $employee);

        $log = AuditLog::where('event', 'attendance.recorded')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame(1, $log->new_values['changed']);
        $this->assertSame(1, $log->new_values['summary']['present']);
    }
}
