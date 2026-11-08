<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\TrainingSession;

/**
 * نسبة حضور الطالب في دورته.
 * - المقام: الجلسات «المنعقدة» في دورته منذ تاريخ تسجيله (الجلسة بلا سجل حضور تُعدّ غيابًا).
 * - البسط: الحاضر، ويُضاف الغياب بعذر إن فعّلتَ EXCUSED_COUNTS_AS_PRESENT.
 * - إن لم تنعقد جلسات بعد فالنسبة غير منطبقة ولا يُمنع الطالب.
 */
class AttendanceCalculator
{
    /** @return array{held: int, present: int, excused: int, absent: int, percent: ?float, minimum: float, below_minimum: bool} */
    public function for(Enrollment $enrollment): array
    {
        $minimum = (float) config('training.min_attendance_percent', 75);

        $sessionIds = TrainingSession::query()
            ->where('course_id', $enrollment->course_id)
            ->where('status', 'held')
            ->whereDate('session_date', '>=', $enrollment->enrolled_at->toDateString())
            ->pluck('id');

        $held = $sessionIds->count();

        if ($held === 0) {
            return ['held' => 0, 'present' => 0, 'excused' => 0, 'absent' => 0, 'percent' => null, 'minimum' => $minimum, 'below_minimum' => false];
        }

        $counts = AttendanceRecord::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('training_session_id', $sessionIds)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);
        $attended = $present + (config('training.excused_counts_as_present') ? $excused : 0);
        $percent = round($attended / $held * 100, 1);

        return [
            'held' => $held,
            'present' => $present,
            'excused' => $excused,
            'absent' => $held - $present - $excused,
            'percent' => $percent,
            'minimum' => $minimum,
            'below_minimum' => $minimum > 0 && $percent < $minimum,
        ];
    }
}
