<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\SubjectTariff;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TrainingFixtures;
use Tests\TestCase;

class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;
    use TrainingFixtures;

    public function test_full_name_is_generated_from_the_four_parts(): void
    {
        $student = $this->makeStudent($this->makeCenter());

        $this->assertSame('أحمد محمد علي حسن', $student->fresh()->full_name);
    }

    public function test_national_id_must_be_nine_digits(): void
    {
        $center = $this->makeCenter();

        $this->expectException(QueryException::class);

        Student::create([
            'national_id' => 'ABC123456',
            'first_name' => 'أ', 'second_name' => 'ب', 'third_name' => 'ج', 'fourth_name' => 'د',
            'phone' => '0599000000', 'address' => 'جنين',
            'governorate_id' => $center->governorate_id, 'center_id' => $center->id,
        ]);
    }

    public function test_national_id_is_unique(): void
    {
        $center = $this->makeCenter();
        $first = $this->makeStudent($center);

        $this->expectException(UniqueConstraintViolationException::class);

        Student::create([
            'national_id' => $first->national_id,
            'first_name' => 'أ', 'second_name' => 'ب', 'third_name' => 'ج', 'fourth_name' => 'د',
            'phone' => '0599000001', 'address' => 'جنين',
            'governorate_id' => $center->governorate_id, 'center_id' => $center->id,
        ]);
    }

    public function test_course_number_is_sequence_slash_year(): void
    {
        $center = $this->makeCenter();
        $course = $this->makeCourse($center, [$this->makeSubject('مادة')]);

        $this->assertSame($course->course_sequence.'/2026', $course->fresh()->course_number);
    }

    public function test_database_allows_only_one_active_enrollment_per_student(): void
    {
        $center = $this->makeCenter();
        $student = $this->makeStudent($center);
        $subject = $this->makeSubject('مادة');
        $courseA = $this->makeCourse($center, [$subject]);
        $courseB = $this->makeCourse($center, [$subject]);

        Enrollment::create(['student_id' => $student->id, 'course_id' => $courseA->id, 'enrolled_at' => today()->toDateString(), 'status' => 'active']);

        // تسجيل غير جارٍ لا يمنع شيئًا.
        Enrollment::create(['student_id' => $student->id, 'course_id' => $courseB->id, 'enrolled_at' => today()->toDateString(), 'status' => 'withdrawn']);

        $this->expectException(UniqueConstraintViolationException::class);

        Enrollment::create(['student_id' => $student->id, 'course_id' => $courseB->id, 'enrolled_at' => today()->toDateString(), 'status' => 'active']);
    }

    public function test_payment_must_reference_exactly_one_target(): void
    {
        $user = $this->makeUser('general_manager');

        $this->expectException(QueryException::class);

        Payment::create([
            'enrollment_subject_id' => null,
            'exam_attempt_id' => null,
            'amount' => '10.00',
            'paid_at' => today()->toDateString(),
            'receipt_number' => 'X1',
            'received_by' => $user->id,
        ]);
    }

    public function test_tariff_requires_a_price(): void
    {
        $subject = $this->makeSubject('مادة', null);

        $this->expectException(QueryException::class);

        SubjectTariff::create([
            'subject_id' => $subject->id,
            'pricing_mode' => 'fixed',
            'fixed_price' => null,
            'lecture_price' => null,
            'lectures_count' => 10,
            'effective_from' => '2020-01-01',
        ]);
    }

    public function test_audit_log_cannot_be_updated_or_deleted(): void
    {
        $log = AuditLog::create([
            'event' => 'test.event',
            'auditable_type' => 'Test',
            'auditable_id' => 1,
        ]);

        $this->assertFalse($log->update(['event' => 'changed']));
        $this->assertFalse($log->delete());
        $this->assertSame('test.event', $log->fresh()->event);
    }
}
