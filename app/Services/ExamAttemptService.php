<?php

namespace App\Services;

use App\Enums\AttemptResult;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Exceptions\BusinessRuleException;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamOverride;
use App\Models\RetakeFee;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ExamAttemptService
{
    /**
     * تسجيل طالب في اختبار. القواعد:
     * - التسجيل جارٍ، والدورة هي دورة الاختبار، والاختبار مجدول ولم يفت موعده.
     * - منع من لديه مواد غير مسددة بعدد العتبة أو أكثر، إلا باستثناء معتمد (ينطبق على الإعادة أيضًا).
     * - لا عملي قبل نجاح معتمَد في النظري.
     * - لا محاولة جديدة إلا بعد رسوب معتمَد في السابقة؛ وللإعادة رسوم تُحفظ على المحاولة.
     */
    public function register(Exam $exam, Enrollment $enrollment, User $actor): ExamAttempt
    {
        $this->assertCan($actor, 'exam_attempts.create');
        $this->assertCenter($actor, (int) $exam->course->center_id);

        if ($exam->status !== ExamStatus::Scheduled) {
            throw new BusinessRuleException('لا يمكن التسجيل في اختبار حالته «'.$exam->status->label().'».');
        }

        if ($exam->exam_date->lt(today())) {
            throw new BusinessRuleException('انتهى موعد هذا الاختبار ('.$exam->exam_date->format('Y-m-d').').');
        }

        if ((int) $enrollment->course_id !== (int) $exam->course_id) {
            throw new BusinessRuleException('هذا الطالب غير مسجّل في دورة هذا الاختبار.');
        }

        try {
            return DB::transaction(function () use ($exam, $enrollment, $actor) {
                $locked = Enrollment::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->status !== EnrollmentStatus::Active) {
                    throw new BusinessRuleException('تسجيل الطالب في الدورة ليس جاريًا.');
                }

                $overridden = ExamOverride::query()
                    ->where('enrollment_id', $locked->id)
                    ->where('exam_id', $exam->id)
                    ->exists();

                if (! $overridden && $locked->isBlockedFromExams()) {
                    $unpaid = $locked->unpaidSubjectsCount();

                    throw new BusinessRuleException(
                        "لا يجوز تسجيل الطالب في الاختبار لوجود {$unpaid} مواد غير مسددة. يلزم استثناء معتمد من رئيس القسم أو المدير."
                    );
                }

                $type = $exam->type;

                if ($type === ExamType::Practical && ! $this->hasApprovedPass($locked, ExamType::Theory)) {
                    throw new BusinessRuleException('لا يجوز دخول الاختبار العملي قبل النجاح (المعتمَد) في الاختبار النظري.');
                }

                $attempts = ExamAttempt::query()
                    ->where('enrollment_id', $locked->id)
                    ->where('exam_type', $type->value)
                    ->orderBy('attempt_no')
                    ->get();

                if ($attempts->contains(fn (ExamAttempt $a) => $a->result === AttemptResult::Passed && $a->approved_at !== null)) {
                    throw new BusinessRuleException('الطالب ناجح في الاختبار '.$type->label().' ولا حاجة لإعادته.');
                }

                $last = $attempts->last();

                if ($last && ! ($last->result === AttemptResult::Failed && $last->approved_at !== null)) {
                    throw new BusinessRuleException('توجد محاولة سابقة لم تُعتمد نتيجتها بعد؛ اعتمد نتيجتها أولًا.');
                }

                $attemptNo = ($last?->attempt_no ?? 0) + 1;
                $fee = null;

                if ($attemptNo > 1) {
                    $retakeFee = RetakeFee::currentFor($type, today());

                    if ($retakeFee === null) {
                        throw new BusinessRuleException('لم تُحدَّد رسوم إعادة للاختبار '.$type->label().'. يحددها المدير العام من شاشة «رسوم الإعادة».');
                    }

                    $fee = $retakeFee->amount;
                }

                $attempt = ExamAttempt::create([
                    'exam_id' => $exam->id,
                    'enrollment_id' => $locked->id,
                    'exam_type' => $type->value,
                    'attempt_no' => $attemptNo,
                    'result' => AttemptResult::Pending->value,
                    'retake_fee_amount' => $fee,
                ]);

                AuditLogger::record('exam_attempt.registered', $attempt, null, $attempt->getAttributes());

                return $attempt;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('الطالب مسجّل في هذا الاختبار مسبقًا، أو تزامن تسجيل آخر؛ أعد المحاولة.');
        }
    }

    /** إدخال النتيجة (ناجح/راسب)، ويمكن تغييرها قبل الاعتماد فقط. */
    public function enterResult(ExamAttempt $attempt, AttemptResult $result, User $actor): ExamAttempt
    {
        $this->assertCan($actor, 'exam_attempts.update');
        $this->assertCenter($actor, (int) $attempt->exam->course->center_id);

        if (! in_array($result, [AttemptResult::Passed, AttemptResult::Failed], true)) {
            throw new BusinessRuleException('النتيجة يجب أن تكون ناجحًا أو راسبًا.');
        }

        return DB::transaction(function () use ($attempt, $result, $actor) {
            $locked = ExamAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();
            $locked->load(['exam', 'payment']);

            if ($locked->approved_at !== null) {
                throw new BusinessRuleException('النتيجة معتمدة ولا يمكن تغييرها.');
            }

            if ($locked->exam->status === ExamStatus::Cancelled) {
                throw new BusinessRuleException('الاختبار ملغى.');
            }

            if ($locked->exam->exam_date->gt(today())) {
                throw new BusinessRuleException('لا تُدخل نتيجة قبل موعد الاختبار ('.$locked->exam->exam_date->format('Y-m-d').').');
            }

            if ((float) $locked->retake_fee_amount > 0 && $locked->payment === null) {
                throw new BusinessRuleException('لم تُدفع رسوم الإعادة لهذه المحاولة؛ تُدفع قبل دخول الاختبار.');
            }

            $old = $locked->only(['result', 'entered_by']);

            $locked->update([
                'result' => $result->value,
                'entered_by' => $actor->id,
            ]);

            AuditLogger::record('exam_attempt.result_entered', $locked, $old, $locked->only(['result', 'entered_by']));

            return $locked;
        });
    }

    /** اعتماد النتيجة؛ وعند اعتماد النجاح في النظري والعملي يُغلق التسجيل بـ«مكتمل». */
    public function approveResult(ExamAttempt $attempt, User $actor): ExamAttempt
    {
        $this->assertCan($actor, 'results.approve');
        $this->assertCenter($actor, (int) $attempt->exam->course->center_id);

        return DB::transaction(function () use ($attempt, $actor) {
            $locked = ExamAttempt::query()->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->result === AttemptResult::Pending) {
                throw new BusinessRuleException('لم تُدخل نتيجة لهذه المحاولة بعد.');
            }

            if ($locked->approved_at !== null) {
                throw new BusinessRuleException('النتيجة معتمدة مسبقًا.');
            }

            $old = $locked->only(['approved_by', 'approved_at']);

            $locked->update([
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            AuditLogger::record('exam_attempt.result_approved', $locked, $old, $locked->only(['approved_by', 'approved_at']));

            $this->completeEnrollmentIfPassedBoth($locked->enrollment()->lockForUpdate()->firstOrFail(), $actor);

            return $locked;
        });
    }

    /** استثناء من منع الاختبار لغير المسدد؛ لرئيس القسم أو المدير بسبب مسجَّل. */
    public function grantOverride(Enrollment $enrollment, Exam $exam, string $reason, User $actor): ExamOverride
    {
        $this->assertCan($actor, 'exam_overrides.create');
        $this->assertCenter($actor, (int) $exam->course->center_id);

        if ((int) $enrollment->course_id !== (int) $exam->course_id) {
            throw new BusinessRuleException('هذا الطالب غير مسجّل في دورة هذا الاختبار.');
        }

        try {
            return DB::transaction(function () use ($enrollment, $exam, $reason, $actor) {
                $override = ExamOverride::create([
                    'enrollment_id' => $enrollment->id,
                    'exam_id' => $exam->id,
                    'reason' => $reason,
                    'approved_by' => $actor->id,
                    'approved_at' => now(),
                ]);

                AuditLogger::record('exam_override.created', $override, null, $override->getAttributes());

                return $override;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('يوجد استثناء معتمد لهذا الطالب في هذا الاختبار مسبقًا.');
        }
    }

    private function hasApprovedPass(Enrollment $enrollment, ExamType $type): bool
    {
        return ExamAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('exam_type', $type->value)
            ->where('result', AttemptResult::Passed->value)
            ->whereNotNull('approved_at')
            ->exists();
    }

    private function completeEnrollmentIfPassedBoth(Enrollment $enrollment, User $actor): void
    {
        if ($enrollment->status !== EnrollmentStatus::Active) {
            return;
        }

        if (! $this->hasApprovedPass($enrollment, ExamType::Theory) || ! $this->hasApprovedPass($enrollment, ExamType::Practical)) {
            return;
        }

        $old = $enrollment->only(['status', 'status_reason']);

        $enrollment->update([
            'status' => EnrollmentStatus::Completed->value,
            'status_reason' => 'اجتاز الاختبارين النظري والعملي',
            'status_changed_by' => $actor->id,
            'status_changed_at' => now(),
        ]);

        AuditLogger::record('enrollment.completed', $enrollment, $old, $enrollment->only(['status', 'status_reason']));
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new BusinessRuleException('ليست لديك صلاحية تنفيذ هذه العملية.');
        }
    }

    private function assertCenter(User $actor, int $centerId): void
    {
        if ($actor->center_id !== null && (int) $actor->center_id !== $centerId) {
            throw new BusinessRuleException('هذا السجل يتبع مركزًا آخر.');
        }
    }
}
