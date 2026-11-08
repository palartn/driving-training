<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * تسجيل/تعديل حضور جلسة لعدة طلاب دفعة واحدة.
     * $entries: [رقم التسجيل => ['status' => present|absent|excused, 'notes' => ?string]]
     *
     * @return array{changed: int, summary: array<string, int>}
     */
    public function record(TrainingSession $session, array $entries, User $actor): array
    {
        $session->loadMissing('course');

        if (! $actor->can('attendance.create')) {
            throw new BusinessRuleException('ليست لديك صلاحية تسجيل الحضور.');
        }

        if ($actor->center_id !== null && (int) $actor->center_id !== (int) $session->course->center_id) {
            throw new BusinessRuleException('هذه الجلسة تتبع مركزًا آخر.');
        }

        if ($session->status === SessionStatus::Cancelled) {
            throw new BusinessRuleException('الجلسة ملغاة.');
        }

        if ($session->session_date->gt(today())) {
            throw new BusinessRuleException('لا يُسجَّل الحضور قبل يوم الجلسة ('.$session->session_date->format('Y-m-d').').');
        }

        $courseClosed = in_array($session->course->status, [CourseStatus::Finished, CourseStatus::Cancelled], true);

        if ($courseClosed && $actor->hierarchyLevel() < 2) {
            throw new BusinessRuleException('الدورة منتهية؛ تعديل الحضور فيها لرئيس القسم فما فوق.');
        }

        if ($entries === []) {
            throw new BusinessRuleException('لا توجد بيانات حضور لحفظها.');
        }

        return DB::transaction(function () use ($session, $entries, $actor) {
            $locked = TrainingSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            $ids = array_map('intval', array_keys($entries));

            $enrollments = Enrollment::query()
                ->whereIn('id', $ids)
                ->where('course_id', $locked->course_id)
                ->get()
                ->keyBy('id');

            $existing = AttendanceRecord::query()
                ->where('training_session_id', $locked->id)
                ->whereIn('enrollment_id', $ids)
                ->get()
                ->keyBy('enrollment_id');

            $changed = 0;

            foreach ($entries as $enrollmentId => $entry) {
                $enrollment = $enrollments->get((int) $enrollmentId);

                if (! $enrollment) {
                    throw new BusinessRuleException('يوجد طالب لا يتبع دورة هذه الجلسة.');
                }

                $status = AttendanceStatus::tryFrom((string) ($entry['status'] ?? ''));

                if ($status === null) {
                    throw new BusinessRuleException('حالة حضور غير صالحة.');
                }

                $notes = filled($entry['notes'] ?? null) ? trim((string) $entry['notes']) : null;
                $record = $existing->get((int) $enrollmentId);

                if ($record) {
                    if ($record->status === $status && $record->notes === $notes) {
                        continue;
                    }

                    if (! $actor->can('attendance.update')) {
                        throw new BusinessRuleException('تعديل حضور مسجَّل يحتاج صلاحية «تعديل الحضور».');
                    }

                    $record->update(['status' => $status->value, 'notes' => $notes, 'recorded_by' => $actor->id]);
                } else {
                    if ($enrollment->status !== EnrollmentStatus::Active) {
                        throw new BusinessRuleException('لا يُسجَّل حضور جديد لطالب تسجيله ليس جاريًا.');
                    }

                    AttendanceRecord::create([
                        'training_session_id' => $locked->id,
                        'enrollment_id' => $enrollment->id,
                        'status' => $status->value,
                        'notes' => $notes,
                        'recorded_by' => $actor->id,
                    ]);
                }

                $changed++;
            }

            if ($locked->status === SessionStatus::Scheduled) {
                $locked->update(['status' => SessionStatus::Held->value]);
            }

            $summary = AttendanceRecord::query()
                ->where('training_session_id', $locked->id)
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->map(fn ($c) => (int) $c)
                ->all();

            AuditLogger::record('attendance.recorded', $locked, null, ['changed' => $changed, 'summary' => $summary]);

            return ['changed' => $changed, 'summary' => $summary];
        });
    }
}
