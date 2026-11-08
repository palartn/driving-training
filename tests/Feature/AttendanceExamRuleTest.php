<?php

namespace Tests\Feature;

use App\Enums\ExamType;
use App\Services\AttendanceCalculator;
use App\Services\AttendanceService;
use App\Services\ExamAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\Support\TrainingSessionFixtures;
use Tests\TestCase;

class AttendanceExamRuleTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;
    use TrainingSessionFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['training.min_attendance_percent' => 75, 'training.excused_counts_as_present' => false]);
    }

    /** @return array{0: \App\Models\Course, 1: \App\Models\User, 2: \App\Models\User, 3: \App\Models\Enrollment, 4: \App\Models\Subject} */
    private function scenario(): array
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('قوانين السير');
        $course = $this->makeCourse($center, [$subject]);
        $enrollment = $this->enroll($this->makeStudent($center), $course, $employee);

        return [$course, $employee, $head, $enrollment, $subject];
    }

    /** يُنشئ جلسات اليوم بأوقات متتالية ويسجّل حضورها بالحالات المعطاة (جلسة لكل حالة). */
    private function heldSessions(array $statuses): void
    {
        [$course, $employee, $head, $enrollment, $subject] = $this->currentScenario;

        foreach (array_values($statuses) as $i => $status) {
            $hour = sprintf('%02d', 6 + $i);
            $session = $this->makeSession($course, $subject, $head, null, ['starts_at' => "{$hour}:00", 'ends_at' => "{$hour}:45"]);

            app(AttendanceService::class)->record($session, [$enrollment->id => ['status' => $status]], $employee);
        }
    }

    private array $currentScenario = [];

    private function register(): \App\Models\ExamAttempt
    {
        [$course, $employee, , $enrollment] = $this->currentScenario;

        return app(ExamAttemptService::class)->register(
            $this->makeExam($course, ExamType::Theory),
            $enrollment->fresh(),
            $employee,
        );
    }

    public function test_the_calculator_reports_percentage_and_threshold(): void
    {
        $this->currentScenario = $this->scenario();
        $this->heldSessions(['present', 'present', 'absent', 'absent']);

        $stats = app(AttendanceCalculator::class)->for($this->currentScenario[3]);

        $this->assertSame(4, $stats['held']);
        $this->assertSame(2, $stats['present']);
        $this->assertSame(2, $stats['absent']);
        $this->assertSame(50.0, $stats['percent']);
        $this->assertTrue($stats['below_minimum']);
    }

    public function test_attendance_below_the_minimum_blocks_the_exam(): void
    {
        $this->currentScenario = $this->scenario();
        $this->heldSessions(['present', 'present', 'absent', 'absent']);

        $this->assertBusinessRule(fn () => $this->register(), 'نسبة حضوره');
    }

    public function test_attendance_at_the_minimum_is_allowed(): void
    {
        $this->currentScenario = $this->scenario();
        $this->heldSessions(['present', 'present', 'present', 'absent']);

        $this->assertSame(1, $this->register()->attempt_no);
    }

    public function test_an_approved_override_lifts_the_attendance_block(): void
    {
        $this->currentScenario = $this->scenario();
        [$course, , $head, $enrollment] = $this->currentScenario;
        $this->heldSessions(['present', 'absent', 'absent', 'absent']);

        $exam = $this->makeExam($course, ExamType::Theory);
        $service = app(ExamAttemptService::class);

        $this->assertBusinessRule(
            fn () => $service->register($exam, $enrollment->fresh(), $this->currentScenario[1]),
            'نسبة حضوره',
        );

        $service->grantOverride($enrollment, $exam, 'ظرف قاهر', $head);

        $this->assertSame(1, $service->register($exam, $enrollment->fresh(), $this->currentScenario[1])->attempt_no);
    }

    public function test_no_held_sessions_means_no_attendance_block(): void
    {
        $this->currentScenario = $this->scenario();

        $this->assertSame(1, $this->register()->attempt_no);
    }

    public function test_the_rule_can_be_disabled(): void
    {
        $this->currentScenario = $this->scenario();
        $this->heldSessions(['absent', 'absent', 'absent', 'absent']);
        config(['training.min_attendance_percent' => 0]);

        $this->assertSame(1, $this->register()->attempt_no);
    }

    public function test_excused_absence_counts_as_attendance_only_when_configured(): void
    {
        $this->currentScenario = $this->scenario();
        $this->heldSessions(['present', 'present', 'excused', 'excused']);

        $this->assertBusinessRule(fn () => $this->register(), 'نسبة حضوره');

        config(['training.excused_counts_as_present' => true]);

        $this->assertSame(1, $this->register()->attempt_no);
    }
}
