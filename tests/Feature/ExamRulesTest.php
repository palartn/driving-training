<?php

namespace Tests\Feature;

use App\Enums\AttemptResult;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamType;
use App\Models\RetakeFee;
use App\Services\ExamAttemptService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class ExamRulesTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    /** @return array{0: \App\Models\Course, 1: \App\Models\User, 2: \App\Models\User, 3: \App\Models\Enrollment} */
    private function scenario(int $subjects = 2): array
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);

        $list = [];
        for ($i = 1; $i <= $subjects; $i++) {
            $list[] = $this->makeSubject('مادة '.$i, '100.00');
        }

        $course = $this->makeCourse($center, $list);
        $enrollment = $this->enroll($this->makeStudent($center), $course, $employee);

        return [$course, $employee, $head, $enrollment];
    }

    private function service(): ExamAttemptService
    {
        return app(ExamAttemptService::class);
    }

    public function test_student_with_two_unpaid_subjects_is_blocked_from_the_exam(): void
    {
        [$course, $employee, , $enrollment] = $this->scenario(2);
        $exam = $this->makeExam($course, ExamType::Theory);

        $this->assertBusinessRule(
            fn () => $this->service()->register($exam, $enrollment, $employee),
            'غير مسددة',
        );
    }

    public function test_one_unpaid_subject_does_not_block_the_exam(): void
    {
        [$course, $employee, , $enrollment] = $this->scenario(2);
        $exam = $this->makeExam($course, ExamType::Theory);

        $first = $enrollment->enrollmentSubjects()->orderBy('id')->first();
        app(PaymentService::class)->recordSubjectPayment($first, $this->payData('100.00', 'R1'), $employee);

        $attempt = $this->service()->register($exam, $enrollment->fresh(), $employee);

        $this->assertSame(1, $attempt->attempt_no);
        $this->assertNull($attempt->retake_fee_amount);
    }

    public function test_an_approved_override_allows_a_blocked_student_and_only_heads_can_grant_it(): void
    {
        [$course, $employee, $head, $enrollment] = $this->scenario(2);
        $exam = $this->makeExam($course, ExamType::Theory);

        $this->assertBusinessRule(
            fn () => $this->service()->grantOverride($enrollment, $exam, 'سبب', $employee),
            'ليست لديك صلاحية',
        );

        $this->service()->grantOverride($enrollment, $exam, 'تأجيل السداد بموافقة', $head);

        $attempt = $this->service()->register($exam, $enrollment->fresh(), $employee);

        $this->assertSame(AttemptResult::Pending, $attempt->result);
    }

    public function test_practical_exam_requires_an_approved_theory_pass(): void
    {
        [$course, $employee, $head, $enrollment] = $this->scenario(1);
        $this->payAll($enrollment, $employee);

        $theory = $this->makeExam($course, ExamType::Theory);
        $practical = $this->makeExam($course, ExamType::Practical);

        $this->assertBusinessRule(
            fn () => $this->service()->register($practical, $enrollment->fresh(), $employee),
            'الاختبار النظري',
        );

        $attempt = $this->service()->register($theory, $enrollment->fresh(), $employee);
        $this->service()->enterResult($attempt, AttemptResult::Passed, $employee);

        // نجاح غير معتمَد لا يكفي.
        $this->assertBusinessRule(
            fn () => $this->service()->register($practical, $enrollment->fresh(), $employee),
            'الاختبار النظري',
        );

        $this->service()->approveResult($attempt->fresh(), $head);

        $this->assertSame(1, $this->service()->register($practical, $enrollment->fresh(), $employee)->attempt_no);
    }

    public function test_retake_needs_a_fee_and_the_fee_must_be_paid_before_the_result(): void
    {
        [$course, $employee, $head, $enrollment] = $this->scenario(1);
        $this->payAll($enrollment, $employee);

        $examOne = $this->makeExam($course, ExamType::Theory);
        $examTwo = $this->makeExam($course, ExamType::Theory);

        $first = $this->service()->register($examOne, $enrollment->fresh(), $employee);
        $this->service()->enterResult($first, AttemptResult::Failed, $employee);
        $this->service()->approveResult($first->fresh(), $head);

        // لا رسوم إعادة محددة بعد.
        $this->assertBusinessRule(
            fn () => $this->service()->register($examTwo, $enrollment->fresh(), $employee),
            'رسوم إعادة',
        );

        RetakeFee::create(['exam_type' => 'theory', 'amount' => '30.00', 'effective_from' => today()->toDateString()]);

        $second = $this->service()->register($examTwo, $enrollment->fresh(), $employee);

        $this->assertSame(2, $second->attempt_no);
        $this->assertSame('30.00', $second->retake_fee_amount);

        $this->assertBusinessRule(
            fn () => $this->service()->enterResult($second, AttemptResult::Passed, $employee),
            'رسوم الإعادة',
        );

        $this->assertBusinessRule(
            fn () => app(PaymentService::class)->recordAttemptPayment($second->fresh(), $this->payData('20.00', 'F1'), $employee),
            'يجب أن يساوي',
        );

        app(PaymentService::class)->recordAttemptPayment($second->fresh(), $this->payData('30.00', 'F1'), $employee);

        $this->assertSame(
            AttemptResult::Passed,
            $this->service()->enterResult($second->fresh(), AttemptResult::Passed, $employee)->result,
        );
    }

    public function test_new_attempt_is_rejected_while_the_previous_one_is_not_approved(): void
    {
        [$course, $employee, , $enrollment] = $this->scenario(1);
        $this->payAll($enrollment, $employee);

        $examOne = $this->makeExam($course, ExamType::Theory);
        $examTwo = $this->makeExam($course, ExamType::Theory);

        $first = $this->service()->register($examOne, $enrollment->fresh(), $employee);
        $this->service()->enterResult($first, AttemptResult::Failed, $employee);

        $this->assertBusinessRule(
            fn () => $this->service()->register($examTwo, $enrollment->fresh(), $employee),
            'محاولة سابقة',
        );
    }

    public function test_result_cannot_be_entered_before_the_exam_date(): void
    {
        [$course, $employee, , $enrollment] = $this->scenario(1);
        $this->payAll($enrollment, $employee);

        $exam = $this->makeExam($course, ExamType::Theory, today()->addDays(5)->toDateString());
        $attempt = $this->service()->register($exam, $enrollment->fresh(), $employee);

        $this->assertBusinessRule(
            fn () => $this->service()->enterResult($attempt, AttemptResult::Passed, $employee),
            'قبل موعد الاختبار',
        );
    }

    public function test_employee_cannot_approve_results(): void
    {
        [$course, $employee, , $enrollment] = $this->scenario(1);
        $this->payAll($enrollment, $employee);

        $attempt = $this->service()->register($this->makeExam($course, ExamType::Theory), $enrollment->fresh(), $employee);
        $this->service()->enterResult($attempt, AttemptResult::Passed, $employee);

        $this->assertBusinessRule(
            fn () => $this->service()->approveResult($attempt->fresh(), $employee),
            'ليست لديك صلاحية',
        );
    }

    public function test_enrollment_completes_after_both_exams_are_passed_and_approved(): void
    {
        [$course, $employee, $head, $enrollment] = $this->scenario(1);
        $this->payAll($enrollment, $employee);

        $theory = $this->service()->register($this->makeExam($course, ExamType::Theory), $enrollment->fresh(), $employee);
        $this->service()->enterResult($theory, AttemptResult::Passed, $employee);
        $this->service()->approveResult($theory->fresh(), $head);

        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);

        $practical = $this->service()->register($this->makeExam($course, ExamType::Practical), $enrollment->fresh(), $employee);
        $this->service()->enterResult($practical, AttemptResult::Passed, $employee);
        $this->service()->approveResult($practical->fresh(), $head);

        $this->assertSame(EnrollmentStatus::Completed, $enrollment->fresh()->status);
    }

    public function test_override_applies_to_retake_attempts_too(): void
    {
        [$course, $employee, $head, $enrollment] = $this->scenario(2);
        $examOne = $this->makeExam($course, ExamType::Theory);
        $examTwo = $this->makeExam($course, ExamType::Theory);

        $this->service()->grantOverride($enrollment, $examOne, 'استثناء', $head);
        $first = $this->service()->register($examOne, $enrollment->fresh(), $employee);
        $this->service()->enterResult($first, AttemptResult::Failed, $employee);
        $this->service()->approveResult($first->fresh(), $head);

        RetakeFee::create(['exam_type' => 'theory', 'amount' => '30.00', 'effective_from' => today()->toDateString()]);

        // الاستثناء كان للاختبار الأول فقط؛ الإعادة في اختبار آخر تُمنع من جديد.
        $this->assertBusinessRule(
            fn () => $this->service()->register($examTwo, $enrollment->fresh(), $employee),
            'غير مسددة',
        );
    }
}
