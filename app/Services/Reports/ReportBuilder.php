<?php

namespace App\Services\Reports;

use App\Enums\AttemptResult;
use App\Models\Center;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentSubject;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * يبني بيانات التقارير كمصفوفة واحدة تُعرض للطباعة (HTML) وتُصدَّر إلى Excel بالمحتوى نفسه.
 * الشكل: key, title, orientation, meta[label=>value], columns[label,type], rows[], totals[label=>number].
 * أنواع الأعمدة: text | number | money.
 */
class ReportBuilder
{
    /** المستخدم المرتبط بمركز يُقيَّد بمركزه مهما كان الطلب. */
    private function center(User $user, ?int $requested): ?int
    {
        return $user->center_id !== null ? (int) $user->center_id : $requested;
    }

    private function assertCenter(User $user, int $centerId): void
    {
        if ($user->center_id !== null && (int) $user->center_id !== $centerId) {
            abort(403);
        }
    }

    private function centerName(?int $centerId): string
    {
        return $centerId === null ? 'كل المراكز' : (string) Center::whereKey($centerId)->value('name');
    }

    private function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /** كشف تسجيل دورة. */
    public function enrollmentRoster(User $user, int $courseId): array
    {
        $course = Course::with(['type', 'center'])->findOrFail($courseId);
        $this->assertCenter($user, (int) $course->center_id);

        $enrollments = Enrollment::query()
            ->where('course_id', $course->id)
            ->with('student')
            ->withCount(['enrollmentSubjects as unpaid_count' => fn (Builder $q) => $q->unpaid()])
            ->orderBy(Student::query()->select('full_name')->whereColumn('students.id', 'enrollments.student_id'))
            ->get();

        return [
            'key' => 'enrollment-roster',
            'title' => 'كشف تسجيل الدورة',
            'orientation' => 'portrait',
            'meta' => [
                'رقم الدورة' => $course->course_number,
                'نوع الدورة' => $course->type?->name,
                'المركز' => $course->center?->name,
                'الفترة' => $course->start_date->format('Y-m-d').' إلى '.$course->end_date->format('Y-m-d'),
                'عدد الطلاب' => $enrollments->count(),
            ],
            'columns' => [
                ['label' => 'رقم الهوية', 'type' => 'text'],
                ['label' => 'اسم الطالب', 'type' => 'text'],
                ['label' => 'الهاتف', 'type' => 'text'],
                ['label' => 'تاريخ التسجيل', 'type' => 'text'],
                ['label' => 'حالة التسجيل', 'type' => 'text'],
                ['label' => 'مواد غير مسددة', 'type' => 'number'],
            ],
            'rows' => $enrollments->map(fn (Enrollment $e) => [
                $e->student->national_id,
                $e->student->full_name,
                $e->student->phone,
                $e->enrolled_at->format('Y-m-d'),
                $e->status->label(),
                (int) $e->unpaid_count,
            ])->all(),
            'totals' => [],
        ];
    }

    /** كشف نتائج اختبار. */
    public function examResults(User $user, int $examId): array
    {
        $exam = Exam::with(['course.type', 'course.center'])->findOrFail($examId);
        $this->assertCenter($user, (int) $exam->course->center_id);

        $attempts = $exam->attempts()
            ->with('enrollment.student')
            ->orderBy(
                Student::query()
                    ->select('full_name')
                    ->join('enrollments', 'enrollments.student_id', '=', 'students.id')
                    ->whereColumn('enrollments.id', 'exam_attempts.enrollment_id')
                    ->limit(1)
            )
            ->get();

        return [
            'key' => 'exam-results',
            'title' => 'كشف نتائج الاختبار',
            'orientation' => 'portrait',
            'meta' => [
                'الاختبار' => $exam->type->label(),
                'الموعد' => $exam->exam_date->format('Y-m-d'),
                'الدورة' => $exam->course->course_number,
                'المركز' => $exam->course->center?->name,
                'عدد المسجّلين' => $attempts->count(),
                'الناجحون (المعتمَدون)' => $attempts->filter(fn ($a) => $a->result === AttemptResult::Passed && $a->approved_at !== null)->count(),
            ],
            'columns' => [
                ['label' => 'رقم الهوية', 'type' => 'text'],
                ['label' => 'اسم الطالب', 'type' => 'text'],
                ['label' => 'المحاولة', 'type' => 'number'],
                ['label' => 'النتيجة', 'type' => 'text'],
                ['label' => 'الاعتماد', 'type' => 'text'],
                ['label' => 'رسوم الإعادة', 'type' => 'money'],
            ],
            'rows' => $attempts->map(fn ($a) => [
                $a->enrollment->student->national_id,
                $a->enrollment->student->full_name,
                (int) $a->attempt_no,
                $a->result->label(),
                $a->result === AttemptResult::Pending ? '-' : ($a->approved_at ? 'معتمدة' : 'بانتظار الاعتماد'),
                $a->retake_fee_amount === null ? null : (float) $a->retake_fee_amount,
            ])->all(),
            'totals' => [],
        ];
    }

    /** كشف الدفعات (إيصالات المواد ورسوم الإعادة) في فترة. */
    public function payments(User $user, ?string $from, ?string $until, ?int $centerId): array
    {
        $centerId = $this->center($user, $centerId);
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : today()->startOfMonth();
        $untilDate = $until ? Carbon::parse($until)->startOfDay() : today();

        $payments = Payment::query()
            ->whereBetween('paid_at', [$fromDate->toDateString(), $untilDate->toDateString()])
            ->when($centerId, fn (Builder $query, int $center) => $query->where(function (Builder $q) use ($center) {
                $q->whereHas('enrollmentSubject.enrollment.course', fn (Builder $c) => $c->where('center_id', $center))
                    ->orWhereHas('examAttempt.exam.course', fn (Builder $c) => $c->where('center_id', $center));
            }))
            ->with([
                'enrollmentSubject.subject',
                'enrollmentSubject.enrollment.student',
                'examAttempt.enrollment.student',
                'receiver',
            ])
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $totalCents = $payments->sum(fn (Payment $p) => $this->cents($p->amount));

        return [
            'key' => 'payments',
            'title' => 'كشف الدفعات',
            'orientation' => 'landscape',
            'meta' => [
                'الفترة' => $fromDate->format('Y-m-d').' إلى '.$untilDate->format('Y-m-d'),
                'المركز' => $this->centerName($centerId),
                'عدد الدفعات' => $payments->count(),
            ],
            'columns' => [
                ['label' => 'رقم الإيصال', 'type' => 'text'],
                ['label' => 'تاريخ الدفع', 'type' => 'text'],
                ['label' => 'رقم الهوية', 'type' => 'text'],
                ['label' => 'اسم الطالب', 'type' => 'text'],
                ['label' => 'البند', 'type' => 'text'],
                ['label' => 'المبلغ', 'type' => 'money'],
                ['label' => 'استلمها', 'type' => 'text'],
            ],
            'rows' => $payments->map(function (Payment $p) {
                $student = $p->enrollmentSubject?->enrollment?->student ?? $p->examAttempt?->enrollment?->student;
                $item = $p->enrollmentSubject
                    ? 'مادة: '.$p->enrollmentSubject->subject->name
                    : 'رسوم إعادة اختبار '.$p->examAttempt->exam_type->label();

                return [
                    $p->receipt_number,
                    $p->paid_at->format('Y-m-d'),
                    $student?->national_id,
                    $student?->full_name,
                    $item,
                    (float) $p->amount,
                    $p->receiver?->name,
                ];
            })->all(),
            'totals' => ['إجمالي المبالغ' => $totalCents / 100],
        ];
    }

    /** المواد غير المسددة (تسجيلات جارية أو مكتملة أو منسحبة). */
    public function unpaidSubjects(User $user, ?int $centerId, ?int $courseId): array
    {
        $centerId = $this->center($user, $centerId);

        $course = null;
        if ($courseId !== null) {
            $course = Course::findOrFail($courseId);
            $this->assertCenter($user, (int) $course->center_id);
        }

        $units = EnrollmentSubject::query()
            ->unpaid()
            ->whereHas('enrollment', fn (Builder $q) => $q
                ->whereIn('status', ['active', 'completed', 'withdrawn'])
                ->when($courseId, fn (Builder $q2, int $id) => $q2->where('course_id', $id))
                ->when($centerId, fn (Builder $q2, int $center) => $q2->whereHas('course', fn (Builder $c) => $c->where('center_id', $center))))
            ->with(['subject', 'exemption', 'enrollment.student', 'enrollment.course'])
            ->get()
            ->sortBy(fn (EnrollmentSubject $u) => [$u->enrollment->student->full_name, $u->id])
            ->values();

        $dueCents = $units->sum(fn (EnrollmentSubject $u) => $u->dueCents());

        return [
            'key' => 'unpaid-subjects',
            'title' => 'كشف المواد غير المسددة',
            'orientation' => 'landscape',
            'meta' => [
                'المركز' => $this->centerName($centerId),
                'الدورة' => $course?->course_number ?? 'كل الدورات',
                'عدد المواد غير المسددة' => $units->count(),
                'عدد الطلاب' => $units->pluck('enrollment.student_id')->unique()->count(),
            ],
            'columns' => [
                ['label' => 'رقم الهوية', 'type' => 'text'],
                ['label' => 'اسم الطالب', 'type' => 'text'],
                ['label' => 'الهاتف', 'type' => 'text'],
                ['label' => 'الدورة', 'type' => 'text'],
                ['label' => 'المادة', 'type' => 'text'],
                ['label' => 'السعر', 'type' => 'money'],
                ['label' => 'الإعفاء', 'type' => 'money'],
                ['label' => 'المستحق', 'type' => 'money'],
            ],
            'rows' => $units->map(fn (EnrollmentSubject $u) => [
                $u->enrollment->student->national_id,
                $u->enrollment->student->full_name,
                $u->enrollment->student->phone,
                $u->enrollment->course->course_number,
                $u->subject->name,
                (float) $u->price_amount,
                $u->exemption ? (float) $u->exemption->exemption_amount : null,
                $u->dueCents() / 100,
            ])->all(),
            'totals' => ['إجمالي المستحق' => $dueCents / 100],
        ];
    }
}
