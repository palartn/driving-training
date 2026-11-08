<?php

namespace App\Services\Reports;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\AttendanceCalculator;
use Illuminate\Database\Eloquent\Builder;

/** تقارير الحضور بنفس صيغة بقية التقارير (تُعرض للطباعة وتُصدَّر إلى Excel). */
class AttendanceReportBuilder
{
    private function assertCenter(User $user, int $centerId): void
    {
        if ($user->center_id !== null && (int) $user->center_id !== $centerId) {
            abort(403);
        }
    }

    /** كشف حضور جلسة: للطباعة والتوقيع، ويُملأ بخط اليد إن لم يُسجَّل الحضور بعد. */
    public function sessionAttendance(User $user, int $sessionId): array
    {
        $session = TrainingSession::with(['course.type', 'course.center', 'subject', 'instructor'])->findOrFail($sessionId);
        $this->assertCenter($user, (int) $session->course->center_id);

        $records = AttendanceRecord::query()
            ->where('training_session_id', $session->id)
            ->get()
            ->keyBy('enrollment_id');

        $enrollments = Enrollment::query()
            ->where('course_id', $session->course_id)
            ->where(fn (Builder $q) => $q->where('status', 'active')->orWhereIn('id', $records->keys()->all()))
            ->with('student')
            ->get()
            ->sortBy(fn (Enrollment $e) => $e->student->full_name)
            ->values();

        return [
            'key' => 'session-attendance',
            'title' => 'كشف حضور جلسة',
            'orientation' => 'portrait',
            'meta' => [
                'الدورة' => $session->course->course_number,
                'نوع الدورة' => $session->course->type?->name,
                'المركز' => $session->course->center?->name,
                'المادة' => $session->subject->name,
                'المدرب' => $session->instructor?->name ?? '-',
                'التاريخ' => $session->session_date->format('Y-m-d'),
                'الوقت' => $session->timeLabel(),
                'المكان' => $session->location ?? '-',
                'نوع الجلسة' => $session->type->label(),
                'حالة الجلسة' => $session->status->label(),
            ],
            'columns' => [
                ['label' => 'رقم الهوية', 'type' => 'text'],
                ['label' => 'اسم الطالب', 'type' => 'text'],
                ['label' => 'الحضور', 'type' => 'text'],
                ['label' => 'ملاحظات', 'type' => 'text'],
                ['label' => 'التوقيع', 'type' => 'text'],
            ],
            // مسافة (وليس نصًا فارغًا) كي تبقى الخلية فارغة في الطباعة لتُملأ بخط اليد.
            'rows' => $enrollments->map(function (Enrollment $e) use ($records) {
                $record = $records->get($e->id);

                return [
                    $e->student->national_id,
                    $e->student->full_name,
                    $record ? $record->status->label() : ' ',
                    $record?->notes ?? ' ',
                    ' ',
                ];
            })->all(),
            'totals' => [],
        ];
    }

    /** ملخص حضور دورة: لكل طالب الجلسات والحاضر والغائب والنسبة وساعات التدريب. */
    public function courseAttendance(User $user, int $courseId): array
    {
        $course = Course::with(['type', 'center'])->findOrFail($courseId);
        $this->assertCenter($user, (int) $course->center_id);

        $heldSessions = TrainingSession::query()
            ->where('course_id', $course->id)
            ->where('status', 'held')
            ->get()
            ->keyBy('id');

        $enrollments = Enrollment::query()
            ->where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed', 'withdrawn'])
            ->with('student')
            ->orderBy(Student::query()->select('full_name')->whereColumn('students.id', 'enrollments.student_id'))
            ->get();

        $presentRecords = AttendanceRecord::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id')->all())
            ->whereIn('training_session_id', $heldSessions->keys()->all())
            ->where('status', 'present')
            ->get()
            ->groupBy('enrollment_id');

        $calculator = app(AttendanceCalculator::class);
        $minimum = (float) config('training.min_attendance_percent', 75);

        $rows = $enrollments->map(function (Enrollment $e) use ($calculator, $presentRecords, $heldSessions) {
            $stats = $calculator->for($e);

            $minutes = 0;
            $practicalMinutes = 0;

            foreach ($presentRecords->get($e->id, collect()) as $record) {
                $session = $heldSessions->get($record->training_session_id);

                if ($session === null || $session->session_date->lt($e->enrolled_at)) {
                    continue;
                }

                $minutes += (int) $session->duration_minutes;

                if ($session->type->value === 'practical') {
                    $practicalMinutes += (int) $session->duration_minutes;
                }
            }

            return [
                $e->student->national_id,
                $e->student->full_name,
                $stats['held'],
                $stats['present'],
                $stats['excused'],
                $stats['absent'],
                $stats['percent'],
                round($minutes / 60, 1),
                round($practicalMinutes / 60, 1),
                $stats['percent'] === null ? '-' : ($stats['below_minimum'] ? 'دون الحد' : 'ضمن الحد'),
            ];
        })->all();

        return [
            'key' => 'course-attendance',
            'title' => 'ملخص حضور الدورة',
            'orientation' => 'landscape',
            'meta' => [
                'رقم الدورة' => $course->course_number,
                'نوع الدورة' => $course->type?->name,
                'المركز' => $course->center?->name,
                'الجلسات المنعقدة' => $heldSessions->count(),
                'الحد الأدنى للحضور' => $minimum > 0 ? $minimum.'%' : 'غير مفعَّل',
                'عدد الطلاب' => count($rows),
            ],
            'columns' => [
                ['label' => 'رقم الهوية', 'type' => 'text'],
                ['label' => 'اسم الطالب', 'type' => 'text'],
                ['label' => 'الجلسات المنعقدة', 'type' => 'number'],
                ['label' => 'حاضر', 'type' => 'number'],
                ['label' => 'بعذر', 'type' => 'number'],
                ['label' => 'غائب', 'type' => 'number'],
                ['label' => 'النسبة %', 'type' => 'number'],
                ['label' => 'ساعات الحضور', 'type' => 'number'],
                ['label' => 'منها عملي', 'type' => 'number'],
                ['label' => 'الحالة', 'type' => 'text'],
            ],
            'rows' => $rows,
            'totals' => [],
        ];
    }
}
