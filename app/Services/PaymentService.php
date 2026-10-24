<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\EnrollmentSubject;
use App\Models\ExamAttempt;
use App\Models\Exemption;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /** دفعة واحدة لكل مادة، ويجب أن تساوي المستحق بعد الإعفاء تمامًا. */
    public function recordSubjectPayment(EnrollmentSubject $subject, array $data, User $actor): Payment
    {
        if (! $actor->can('payments.create')) {
            throw new BusinessRuleException('ليست لديك صلاحية تسجيل الدفعات.');
        }

        $this->assertCenter((int) $subject->enrollment->course->center_id, $actor);

        try {
            return DB::transaction(function () use ($subject, $data, $actor) {
                $locked = EnrollmentSubject::query()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
                $locked->load(['payment', 'exemption']);

                if ($locked->payment) {
                    throw new BusinessRuleException('سُجّلت دفعة لهذه المادة مسبقًا.');
                }

                $due = $locked->dueCents();

                if ($due === 0) {
                    throw new BusinessRuleException('هذه المادة معافاة بالكامل ولا تحتاج إلى دفعة.');
                }

                if ($this->cents($data['amount']) !== $due) {
                    throw new BusinessRuleException('يجب أن يساوي المبلغ المدفوع المستحق تمامًا: '.$this->money($due).'.');
                }

                $payment = Payment::create([
                    'enrollment_subject_id' => $locked->id,
                    'amount' => $this->decimal($due),
                    'paid_at' => $data['paid_at'],
                    'receipt_number' => trim((string) $data['receipt_number']),
                    'received_by' => $actor->id,
                    'notes' => $data['notes'] ?? null,
                ]);

                AuditLogger::record('payment.created', $payment, null, $payment->getAttributes());

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('رقم الإيصال مستخدم مسبقًا، أو سُجّلت دفعة لهذه المادة.');
        }
    }

    /** رسوم إعادة الاختبار: دفعة واحدة لكل محاولة بمبلغ الرسوم المحفوظ على المحاولة تمامًا. */
    public function recordAttemptPayment(ExamAttempt $attempt, array $data, User $actor): Payment
    {
        if (! $actor->can('payments.create')) {
            throw new BusinessRuleException('ليست لديك صلاحية تسجيل الدفعات.');
        }

        $this->assertCenter((int) $attempt->exam->course->center_id, $actor);

        try {
            return DB::transaction(function () use ($attempt, $data, $actor) {
                $locked = ExamAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();
                $locked->load('payment');

                $fee = $this->cents($locked->retake_fee_amount);

                if ($fee <= 0) {
                    throw new BusinessRuleException('لا توجد رسوم إعادة على هذه المحاولة.');
                }

                if ($locked->payment) {
                    throw new BusinessRuleException('سُدِّدت رسوم هذه المحاولة مسبقًا.');
                }

                if ($this->cents($data['amount']) !== $fee) {
                    throw new BusinessRuleException('يجب أن يساوي المبلغ رسوم الإعادة تمامًا: '.$this->money($fee).'.');
                }

                $payment = Payment::create([
                    'exam_attempt_id' => $locked->id,
                    'amount' => $this->decimal($fee),
                    'paid_at' => $data['paid_at'],
                    'receipt_number' => trim((string) $data['receipt_number']),
                    'received_by' => $actor->id,
                    'notes' => $data['notes'] ?? null,
                ]);

                AuditLogger::record('payment.retake_fee.created', $payment, null, $payment->getAttributes());

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('رقم الإيصال مستخدم مسبقًا، أو سُدِّدت رسوم هذه المحاولة.');
        }
    }

    /** إعفاء (كلي أو جزئي بمبلغ) مع السبب والمعتمِد، ولا يجوز بعد تسجيل دفعة. */
    public function addExemption(EnrollmentSubject $subject, mixed $amount, string $reason, User $actor): Exemption
    {
        if (! $actor->can('exemptions.create')) {
            throw new BusinessRuleException('ليست لديك صلاحية اعتماد الإعفاءات.');
        }

        $this->assertCenter((int) $subject->enrollment->course->center_id, $actor);

        try {
            return DB::transaction(function () use ($subject, $amount, $reason, $actor) {
                $locked = EnrollmentSubject::query()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();
                $locked->load(['payment', 'exemption']);

                if ($locked->payment) {
                    throw new BusinessRuleException('لا يمكن إضافة إعفاء بعد تسجيل دفعة لهذه المادة.');
                }

                if ($locked->exemption) {
                    throw new BusinessRuleException('يوجد إعفاء مسجّل لهذه المادة مسبقًا.');
                }

                $cents = $this->cents($amount);
                $price = $this->cents($locked->price_amount);

                if ($cents <= 0) {
                    throw new BusinessRuleException('مبلغ الإعفاء يجب أن يكون أكبر من صفر.');
                }

                if ($cents > $price) {
                    throw new BusinessRuleException('مبلغ الإعفاء لا يتجاوز سعر المادة ('.$this->money($price).').');
                }

                $exemption = Exemption::create([
                    'enrollment_subject_id' => $locked->id,
                    'exemption_amount' => $this->decimal($cents),
                    'reason' => $reason,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                    'created_by' => $actor->id,
                ]);

                AuditLogger::record('exemption.created', $exemption, null, $exemption->getAttributes());

                return $exemption;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('يوجد إعفاء مسجّل لهذه المادة مسبقًا.');
        }
    }

    private function assertCenter(int $centerId, User $actor): void
    {
        if ($actor->center_id !== null && (int) $actor->center_id !== $centerId) {
            throw new BusinessRuleException('هذا السجل يتبع مركزًا آخر.');
        }
    }

    private function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2).' '.config('training.currency_symbol', '₪');
    }
}
