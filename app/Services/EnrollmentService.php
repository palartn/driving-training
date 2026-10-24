<?php

namespace App\Services;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentSubject;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    /**
     * تسجيل طالب في دورة: ينشئ وحدة مالية لكل مادة بسعر التعريفة السارية في تاريخ التسجيل.
     * كل شيء داخل معاملة واحدة: إما أن يكتمل أو لا يُحفظ شيء.
     */
    public function enroll(Student $student, Course $course, CarbonInterface $date, User $actor): Enrollment
    {
        if (! $actor->can('enrollments.create')) {
            throw new BusinessRuleException('ليست لديك صلاحية تسجيل الطلاب.');
        }

        if ($actor->center_id !== null && (int) $actor->center_id !== (int) $student->center_id) {
            throw new BusinessRuleException('لا يمكنك تسجيل طالب يتبع مركزًا آخر.');
        }

        if ((int) $student->center_id !== (int) $course->center_id) {
            throw new BusinessRuleException('الطالب والدورة يتبعان مركزين مختلفين.');
        }

        if (! in_array($course->status, [CourseStatus::Upcoming, CourseStatus::Running], true)) {
            throw new BusinessRuleException('لا يمكن التسجيل في دورة حالتها «'.$course->status->label().'».');
        }

        $subjects = $course->subjects()->orderBy('subjects.id')->get();

        if ($subjects->isEmpty()) {
            throw new BusinessRuleException('لا توجد مواد مرتبطة بهذه الدورة، أضف موادها أولًا.');
        }

        $tariffs = [];
        $missing = [];
        foreach ($subjects as $subject) {
            $tariff = $subject->tariffOn($date);
            if ($tariff === null) {
                $missing[] = $subject->name;
            } else {
                $tariffs[$subject->id] = $tariff;
            }
        }

        if ($missing !== []) {
            throw new BusinessRuleException('لا يوجد سعر ساري بتاريخ '.$date->format('Y-m-d').' للمواد: '.implode('، ', $missing).'. أضف سعرها من شاشة «المواد والتعريفة».');
        }

        try {
            return DB::transaction(function () use ($student, $course, $date, $actor, $subjects, $tariffs) {
                $enrollment = Enrollment::create([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'enrolled_at' => $date->toDateString(),
                    'status' => EnrollmentStatus::Active->value,
                    'created_by' => $actor->id,
                ]);

                foreach ($subjects as $subject) {
                    $tariff = $tariffs[$subject->id];

                    EnrollmentSubject::create([
                        'enrollment_id' => $enrollment->id,
                        'subject_id' => $subject->id,
                        'tariff_id' => $tariff->id,
                        'lectures_count' => $tariff->lectures_count,
                        'price_amount' => $tariff->total_price,
                    ]);
                }

                AuditLogger::record('enrollment.created', $enrollment, null, $enrollment->getAttributes());

                return $enrollment;
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('الطالب مسجّل حاليًا في دورة جارية، ولا يمكن تسجيله في دورة أخرى قبل إغلاق تسجيله الحالي.');
        }
    }

    /** إغلاق يدوي للتسجيل (انسحاب أو إلغاء) بسبب مسجَّل ومعتمِد. */
    public function close(Enrollment $enrollment, EnrollmentStatus $to, string $reason, User $actor): Enrollment
    {
        if (! $actor->can('enrollments.close')) {
            throw new BusinessRuleException('ليست لديك صلاحية إغلاق التسجيل.');
        }

        if ($actor->center_id !== null && (int) $actor->center_id !== (int) $enrollment->course->center_id) {
            throw new BusinessRuleException('هذا التسجيل يتبع مركزًا آخر.');
        }

        if (! in_array($to, [EnrollmentStatus::Withdrawn, EnrollmentStatus::Cancelled], true)) {
            throw new BusinessRuleException('نوع الإغلاق غير صالح.');
        }

        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw new BusinessRuleException('التسجيل مغلق مسبقًا.');
        }

        $old = $enrollment->only(['status', 'status_reason']);

        $enrollment->update([
            'status' => $to->value,
            'status_reason' => $reason,
            'status_changed_by' => $actor->id,
            'status_changed_at' => now(),
        ]);

        AuditLogger::record('enrollment.closed', $enrollment, $old, $enrollment->only(['status', 'status_reason']));

        return $enrollment;
    }
}
