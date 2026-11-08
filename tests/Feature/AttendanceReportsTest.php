<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\AttendanceService;
use App\Services\Reports\AttendanceReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TrainingFixtures;
use Tests\Support\TrainingSessionFixtures;
use Tests\TestCase;

class AttendanceReportsTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;
    use TrainingSessionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_course_summary_counts_sessions_percentage_and_hours(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('قوانين السير');
        $course = $this->makeCourse($center, [$subject]);
        $enrollment = $this->enroll($this->makeStudent($center), $course, $employee);

        $theory = $this->makeSession($course, $subject, $head, null, ['starts_at' => '08:00', 'ends_at' => '09:00', 'type' => 'theory']);
        $practical = $this->makeSession($course, $subject, $head, null, ['starts_at' => '09:00', 'ends_at' => '10:00', 'type' => 'practical']);

        foreach ([$theory, $practical] as $session) {
            app(AttendanceService::class)->record($session, [$enrollment->id => ['status' => 'present']], $employee);
        }

        $report = app(AttendanceReportBuilder::class)->courseAttendance($employee, $course->id);

        $this->assertCount(1, $report['rows']);

        [, , $held, $present, $excused, $absent, $percent, $hours, $practicalHours, $state] = $report['rows'][0];

        $this->assertSame(2, $held);
        $this->assertSame(2, $present);
        $this->assertSame(0, $excused);
        $this->assertSame(0, $absent);
        $this->assertEquals(100.0, $percent);
        $this->assertEquals(2.0, $hours);
        $this->assertEquals(1.0, $practicalHours);
        $this->assertSame('ضمن الحد', $state);
    }

    public function test_session_sheet_leaves_unrecorded_students_blank_for_hand_filling(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('أ');
        $course = $this->makeCourse($center, [$subject]);
        $enrollment = $this->enroll($this->makeStudent($center), $course, $employee);
        $session = $this->makeSession($course, $subject, $head);

        $before = app(AttendanceReportBuilder::class)->sessionAttendance($employee, $session->id);
        $this->assertSame(' ', $before['rows'][0][2]);

        app(AttendanceService::class)->record($session, [$enrollment->id => ['status' => 'present']], $employee);

        $after = app(AttendanceReportBuilder::class)->sessionAttendance($employee, $session->id);
        $this->assertSame('حاضر', $after['rows'][0][2]);
    }

    public function test_a_center_bound_user_cannot_report_on_another_centers_session(): void
    {
        $centerA = $this->makeCenter('A');
        $centerB = $this->makeCenter('B');
        $head = $this->makeUser('department_head', $centerA);
        $outsider = $this->makeUser('employee', $centerB);
        $subject = $this->makeSubject('أ');
        $course = $this->makeCourse($centerA, [$subject]);
        $session = $this->makeSession($course, $subject, $head);

        $this->expectException(HttpException::class);

        app(AttendanceReportBuilder::class)->sessionAttendance($outsider, $session->id);
    }

    public function test_print_page_shows_the_letterhead_and_the_generation_is_audited(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('أ');
        $course = $this->makeCourse($center, [$subject]);
        $session = $this->makeSession($course, $subject, $head);

        $this->actingAs($employee)
            ->get(route('reports.session-attendance', ['session_id' => $session->id]))
            ->assertOk()
            ->assertSee('State of Palestine')
            ->assertSee('كشف حضور جلسة');

        $this->assertSame(1, AuditLog::where('event', 'report.generated')->count());
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reports.course-attendance', ['course_id' => 1]))->assertRedirect(route('login'));
    }
}
