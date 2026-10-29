<?php

namespace Tests\Feature;

use App\Enums\FinancialStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentSubject;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class UnpaidScopeTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_unpaid_scope_matches_the_financial_status_logic(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);

        $subjects = [];
        for ($i = 1; $i <= 4; $i++) {
            $subjects[] = $this->makeSubject('مادة '.$i, '100.00');
        }

        $enrollment = $this->enroll($this->makeStudent($center), $this->makeCourse($center, $subjects), $employee);
        [$paid, $fullyExempt, $partlyExempt, $untouched] = $enrollment->enrollmentSubjects()->orderBy('id')->get()->all();

        app(PaymentService::class)->recordSubjectPayment($paid, $this->payData('100.00', 'R1'), $employee);
        app(PaymentService::class)->addExemption($fullyExempt, '100.00', 'إعفاء كامل', $head);
        app(PaymentService::class)->addExemption($partlyExempt, '40.00', 'إعفاء جزئي', $head);

        $expected = $enrollment->enrollmentSubjects()->with(['payment', 'exemption'])->get()
            ->filter(fn (EnrollmentSubject $unit) => $unit->financialStatus() === FinancialStatus::Unpaid)
            ->pluck('id')->sort()->values()->all();

        $actual = EnrollmentSubject::unpaid()
            ->where('enrollment_id', $enrollment->id)
            ->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $actual);
        $this->assertSame($expected, $actual);
        $this->assertContains($partlyExempt->id, $actual);
        $this->assertContains($untouched->id, $actual);
    }

    public function test_blocked_enrollments_query_matches_the_blocking_rule(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $subjects = [$this->makeSubject('أ'), $this->makeSubject('ب')];

        $blocked = $this->enroll($this->makeStudent($center), $this->makeCourse($center, $subjects), $employee);
        $fine = $this->enroll($this->makeStudent($center), $this->makeCourse($center, $subjects), $employee);
        $this->payAll($fine, $employee);

        $ids = Enrollment::query()->active()
            ->whereHas('enrollmentSubjects', fn ($q) => $q->unpaid(), '>=', 2)
            ->pluck('id')->all();

        $this->assertContains($blocked->id, $ids);
        $this->assertNotContains($fine->id, $ids);
        $this->assertTrue($blocked->fresh()->isBlockedFromExams());
        $this->assertFalse($fine->fresh()->isBlockedFromExams());
    }
}
