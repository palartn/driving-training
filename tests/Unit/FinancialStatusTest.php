<?php

namespace Tests\Unit;

use App\Enums\FinancialStatus;
use App\Models\EnrollmentSubject;
use App\Models\Exemption;
use App\Models\Payment;
use PHPUnit\Framework\TestCase;

class FinancialStatusTest extends TestCase
{
    private function subject(string $price, ?string $exemption = null, bool $paid = false): EnrollmentSubject
    {
        $subject = new EnrollmentSubject(['price_amount' => $price]);
        $subject->setRelation('exemption', $exemption !== null ? new Exemption(['exemption_amount' => $exemption]) : null);
        $subject->setRelation('payment', $paid ? new Payment(['amount' => '1.00']) : null);

        return $subject;
    }

    public function test_unpaid_when_no_payment_and_no_exemption(): void
    {
        $this->assertSame(FinancialStatus::Unpaid, $this->subject('100.00')->financialStatus());
    }

    public function test_paid_when_payment_exists(): void
    {
        $this->assertSame(FinancialStatus::Paid, $this->subject('100.00', null, true)->financialStatus());
    }

    public function test_exempt_when_exemption_covers_full_price(): void
    {
        $this->assertSame(FinancialStatus::Exempt, $this->subject('100.00', '100.00')->financialStatus());
    }

    public function test_partial_exemption_without_payment_is_unpaid(): void
    {
        $subject = $this->subject('100.00', '40.00');

        $this->assertSame(6000, $subject->dueCents());
        $this->assertSame(FinancialStatus::Unpaid, $subject->financialStatus());
    }

    public function test_partial_exemption_with_payment_is_paid(): void
    {
        $this->assertSame(FinancialStatus::Paid, $this->subject('100.00', '40.00', true)->financialStatus());
    }

    public function test_due_never_goes_below_zero(): void
    {
        $this->assertSame(0, $this->subject('50.00', '80.00')->dueCents());
    }
}
