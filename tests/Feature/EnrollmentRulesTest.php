<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\FinancialStatus;
use App\Models\SubjectTariff;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class EnrollmentRulesTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_enrollment_creates_one_financial_unit_per_subject_with_price_snapshot(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $a = $this->makeSubject('أ', '100.00');
        $b = $this->makeSubject('ب', '50.00');
        $course = $this->makeCourse($center, [$a, $b]);

        $enrollment = $this->enroll($this->makeStudent($center), $course, $employee);

        $units = $enrollment->enrollmentSubjects()->with(['payment', 'exemption'])->get()->keyBy('subject_id');

        $this->assertCount(2, $units);
        $this->assertSame('100.00', $units[$a->id]->price_amount);
        $this->assertSame('50.00', $units[$b->id]->price_amount);
        $this->assertSame(FinancialStatus::Unpaid, $units[$a->id]->financialStatus());
    }

    public function test_later_tariff_change_does_not_affect_existing_enrollment(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $subject = $this->makeSubject('أ', '100.00');
        $course = $this->makeCourse($center, [$subject]);

        $first = $this->enroll($this->makeStudent($center), $course, $employee);

        SubjectTariff::create([
            'subject_id' => $subject->id,
            'pricing_mode' => 'fixed',
            'fixed_price' => '200.00',
            'lecture_price' => null,
            'lectures_count' => 10,
            'effective_from' => today()->toDateString(),
        ]);

        $second = $this->enroll($this->makeStudent($center), $course, $employee);

        $this->assertSame('100.00', $first->enrollmentSubjects()->first()->price_amount);
        $this->assertSame('200.00', $second->enrollmentSubjects()->first()->price_amount);
    }

    public function test_student_cannot_have_two_active_enrollments(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $subject = $this->makeSubject('أ');
        $student = $this->makeStudent($center);

        $this->enroll($student, $this->makeCourse($center, [$subject]), $employee);

        $this->assertBusinessRule(
            fn () => $this->enroll($student, $this->makeCourse($center, [$subject]), $employee),
            'دورة جارية',
        );
    }

    public function test_enrollment_is_rejected_when_a_subject_has_no_price(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $course = $this->makeCourse($center, [$this->makeSubject('بلا سعر', null)]);

        $this->assertBusinessRule(
            fn () => $this->enroll($this->makeStudent($center), $course, $employee),
            'لا يوجد سعر ساري',
        );
    }

    public function test_student_and_course_must_share_a_center(): void
    {
        $centerA = $this->makeCenter('A');
        $centerB = $this->makeCenter('B');
        $actor = $this->makeUser('general_manager');
        $course = $this->makeCourse($centerB, [$this->makeSubject('أ')]);

        $this->assertBusinessRule(
            fn () => $this->enroll($this->makeStudent($centerA), $course, $actor),
            'مركزين مختلفين',
        );
    }

    public function test_finished_course_rejects_new_enrollments(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $course = $this->makeCourse($center, [$this->makeSubject('أ')], 'finished');

        $this->assertBusinessRule(
            fn () => $this->enroll($this->makeStudent($center), $course, $employee),
            'لا يمكن التسجيل',
        );
    }

    public function test_closing_an_enrollment_frees_the_student_for_a_new_one(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $head = $this->makeUser('department_head', $center);
        $subject = $this->makeSubject('أ');
        $student = $this->makeStudent($center);

        $first = $this->enroll($student, $this->makeCourse($center, [$subject]), $employee);

        app(EnrollmentService::class)->close($first, EnrollmentStatus::Withdrawn, 'انسحاب لظرف خاص', $head);

        $second = $this->enroll($student, $this->makeCourse($center, [$subject]), $employee);

        $this->assertSame(EnrollmentStatus::Withdrawn, $first->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $second->fresh()->status);
    }

    public function test_employee_cannot_close_an_enrollment(): void
    {
        $center = $this->makeCenter();
        $employee = $this->makeUser('employee', $center);
        $enrollment = $this->enroll($this->makeStudent($center), $this->makeCourse($center, [$this->makeSubject('أ')]), $employee);

        $this->assertBusinessRule(
            fn () => app(EnrollmentService::class)->close($enrollment, EnrollmentStatus::Cancelled, 'سبب', $employee),
            'ليست لديك صلاحية',
        );
    }

    public function test_employee_of_another_center_cannot_enroll_the_student(): void
    {
        $centerA = $this->makeCenter('A');
        $centerB = $this->makeCenter('B');
        $outsider = $this->makeUser('employee', $centerB);
        $course = $this->makeCourse($centerA, [$this->makeSubject('أ')]);

        $this->assertBusinessRule(
            fn () => $this->enroll($this->makeStudent($centerA), $course, $outsider),
            'مركزًا آخر',
        );
    }
}
