<?php

namespace Tests\Feature;

use App\Enums\FinancialStatus;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class PaymentRulesTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @return array{0: \App\Models\Center, 1: \App\Models\User, 2: \App\Models\User, 3: \App\Models\Enrollment} */
    private function scenario(int $subjects = 1): array
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);

        $list = [];
        for ($i = 1; $i <= $subjects; $i++) {
            $list[] = $this->makeSubject('مادة '.$i, '100.00');
        }

        $enrollment = $this->enroll($this->makeStudent($center), $this->makeCourse($center, $list), $employee);

        return [$center, $employee, $head, $enrollment];
    }

    private function status($unit): FinancialStatus
    {
        return $unit->fresh(['payment', 'exemption'])->financialStatus();
    }

    public function test_payment_must_equal_the_due_amount_exactly(): void
    {
        [, $employee, , $enrollment] = $this->scenario();
        $unit = $enrollment->enrollmentSubjects()->first();

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->recordSubjectPayment($unit, $this->payData('90.00', 'R1'), $employee),
            'يجب أن يساوي',
        );

        app(PaymentService::class)->recordSubjectPayment($unit, $this->payData('100.00', 'R1'), $employee);

        $this->assertSame(FinancialStatus::Paid, $this->status($unit));
    }

    public function test_only_one_payment_per_subject(): void
    {
        [, $employee, , $enrollment] = $this->scenario();
        $unit = $enrollment->enrollmentSubjects()->first();

        app(PaymentService::class)->recordSubjectPayment($unit, $this->payData('100.00', 'R1'), $employee);

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->recordSubjectPayment($unit->fresh(), $this->payData('100.00', 'R2'), $employee),
            'دفعة لهذه المادة',
        );
    }

    public function test_receipt_number_is_unique_across_payments(): void
    {
        [, $employee, , $enrollment] = $this->scenario(2);
        [$first, $second] = $enrollment->enrollmentSubjects()->orderBy('id')->get()->all();

        app(PaymentService::class)->recordSubjectPayment($first, $this->payData('100.00', 'SAME'), $employee);

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->recordSubjectPayment($second, $this->payData('100.00', 'SAME'), $employee),
            'رقم الإيصال مستخدم',
        );
    }

    public function test_partial_exemption_then_payment_of_the_remainder(): void
    {
        [, $employee, $head, $enrollment] = $this->scenario();
        $unit = $enrollment->enrollmentSubjects()->first();

        app(PaymentService::class)->addExemption($unit, '40.00', 'ظرف اجتماعي', $head);

        $this->assertSame(FinancialStatus::Unpaid, $this->status($unit));

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->recordSubjectPayment($unit->fresh(), $this->payData('100.00', 'R1'), $employee),
            'يجب أن يساوي',
        );

        app(PaymentService::class)->recordSubjectPayment($unit->fresh(), $this->payData('60.00', 'R1'), $employee);

        $this->assertSame(FinancialStatus::Paid, $this->status($unit));
    }

    public function test_full_exemption_makes_the_subject_exempt_and_rejects_payment(): void
    {
        [, $employee, $head, $enrollment] = $this->scenario();
        $unit = $enrollment->enrollmentSubjects()->first();

        app(PaymentService::class)->addExemption($unit, '100.00', 'إعفاء كامل', $head);

        $this->assertSame(FinancialStatus::Exempt, $this->status($unit));

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->recordSubjectPayment($unit->fresh(), $this->payData('0.00', 'R1'), $employee),
            'معافاة بالكامل',
        );
    }

    public function test_exemption_is_rejected_after_a_payment(): void
    {
        [, $employee, $head, $enrollment] = $this->scenario();
        $unit = $enrollment->enrollmentSubjects()->first();

        app(PaymentService::class)->recordSubjectPayment($unit, $this->payData('100.00', 'R1'), $employee);

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->addExemption($unit->fresh(), '10.00', 'سبب', $head),
            'بعد تسجيل دفعة',
        );
    }

    public function test_exemption_cannot_exceed_the_price(): void
    {
        [, , $head, $enrollment] = $this->scenario();

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->addExemption($enrollment->enrollmentSubjects()->first(), '150.00', 'سبب', $head),
            'لا يتجاوز سعر المادة',
        );
    }

    public function test_employee_cannot_grant_an_exemption(): void
    {
        [, $employee, , $enrollment] = $this->scenario();

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->addExemption($enrollment->enrollmentSubjects()->first(), '10.00', 'سبب', $employee),
            'ليست لديك صلاحية',
        );
    }
}
