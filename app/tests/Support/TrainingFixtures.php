<?php

namespace Tests\Support;

use App\Enums\ExamType;
use App\Exceptions\BusinessRuleException;
use App\Models\Center;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Governorate;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectTariff;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

trait TrainingFixtures
{
    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function makeCenter(string $code = 'C1'): Center
    {
        return Center::create([
            'name' => 'مركز '.$code,
            'code' => $code,
            'governorate_id' => Governorate::firstOrCreate(['name' => 'جنين'], ['sort_order' => 1])->id,
        ]);
    }

    protected function makeUser(string $role, ?Center $center = null): User
    {
        static $counter = 0;
        $counter++;

        $user = User::create([
            'name' => 'مستخدم '.$counter,
            'username' => 'user'.$counter,
            'password' => 'secret-pass-123',
            'center_id' => $center?->id,
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user->fresh();
    }

    protected function makeStudent(Center $center): Student
    {
        return Student::create([
            'national_id' => str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
            'first_name' => 'أحمد',
            'second_name' => 'محمد',
            'third_name' => 'علي',
            'fourth_name' => 'حسن',
            'phone' => '0599000000',
            'address' => 'جنين',
            'governorate_id' => $center->governorate_id,
            'center_id' => $center->id,
        ]);
    }

    /** مادة بتعريفة سارية منذ 2020 (أو بلا تعريفة إن كان السعر null). */
    protected function makeSubject(string $name, ?string $price = '100.00'): Subject
    {
        $subject = Subject::create(['name' => $name]);

        if ($price !== null) {
            SubjectTariff::create([
                'subject_id' => $subject->id,
                'pricing_mode' => 'fixed',
                'fixed_price' => $price,
                'lecture_price' => null,
                'lectures_count' => 10,
                'effective_from' => '2020-01-01',
            ]);
        }

        return $subject;
    }

    protected function makeCourse(Center $center, array $subjects, string $status = 'running'): Course
    {
        $year = 2026;

        $course = Course::create([
            'course_type_id' => CourseType::firstOrCreate(['name' => 'عمومي'])->id,
            'center_id' => $center->id,
            'course_sequence' => (int) Course::where('course_year', $year)->max('course_sequence') + 1,
            'course_year' => $year,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(30)->toDateString(),
            'status' => $status,
        ]);

        $course->subjects()->attach(collect($subjects)->pluck('id')->all());

        return $course;
    }

    protected function makeExam(Course $course, ExamType $type, ?string $date = null): Exam
    {
        return Exam::create([
            'course_id' => $course->id,
            'type' => $type->value,
            'exam_date' => $date ?? today()->toDateString(),
            'status' => 'scheduled',
        ]);
    }

    protected function enroll(Student $student, Course $course, User $actor): Enrollment
    {
        return app(EnrollmentService::class)->enroll($student, $course, today(), $actor);
    }

    protected function payData(string $amount, string $receipt): array
    {
        return [
            'amount' => $amount,
            'paid_at' => today()->toDateString(),
            'receipt_number' => $receipt,
            'notes' => null,
        ];
    }

    /** يسدد كل مواد التسجيل بالمستحق تمامًا. */
    protected function payAll(Enrollment $enrollment, User $actor): void
    {
        foreach ($enrollment->enrollmentSubjects()->get() as $unit) {
            app(PaymentService::class)->recordSubjectPayment(
                $unit,
                $this->payData(number_format($unit->dueCents() / 100, 2, '.', ''), 'R'.$unit->id.'-'.Str::random(5)),
                $actor,
            );
        }
    }

    /** يتوقع BusinessRuleException تحوي رسالته الجزء المعطى. */
    protected function assertBusinessRule(callable $callback, string $fragment): void
    {
        try {
            $callback();
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());

            return;
        }

        $this->fail('Expected a BusinessRuleException containing: '.$fragment);
    }
}
