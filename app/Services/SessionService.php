<?php

namespace App\Services;

use App\Enums\CourseStatus;
use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Course;
use App\Models\CourseSubjectInstructor;
use App\Models\Instructor;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionService
{
    /** إنشاء جلسة واحدة. $data: course_id, subject_id, instructor_id?, session_date, starts_at, ends_at, type, location?, notes? */
    public function create(array $data, User $actor): TrainingSession
    {
        $this->assertCan($actor, 'training_sessions.create');

        return DB::transaction(fn () => TrainingSession::create(
            $this->prepare($data, $actor, null) + ['status' => SessionStatus::Scheduled->value, 'created_by' => $actor->id]
        ));
    }

    /** تعديل جلسة؛ لا تُغيَّر دورتها ولا مادتها بعد تسجيل حضور فيها. */
    public function update(TrainingSession $session, array $data, User $actor): TrainingSession
    {
        $this->assertCan($actor, 'training_sessions.update');

        return DB::transaction(function () use ($session, $data, $actor) {
            $locked = TrainingSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $hasAttendance = $locked->attendanceRecords()->exists();

            if ($hasAttendance && (
                (int) $data['course_id'] !== (int) $locked->course_id
                || (int) $data['subject_id'] !== (int) $locked->subject_id
            )) {
                throw new BusinessRuleException('لا تُغيَّر دورة الجلسة ولا مادتها بعد تسجيل حضور فيها.');
            }

            $status = SessionStatus::tryFrom((string) ($data['status'] ?? $locked->status->value)) ?? $locked->status;

            if ($status === SessionStatus::Cancelled && $hasAttendance) {
                throw new BusinessRuleException('لا تُلغى جلسة سُجّل فيها حضور.');
            }

            if ($status === SessionStatus::Scheduled && $hasAttendance) {
                throw new BusinessRuleException('الجلسة منعقدة (سُجّل فيها حضور) ولا تعود إلى «مجدولة».');
            }

            $locked->update($this->prepare($data, $actor, $locked) + ['status' => $status->value]);

            return $locked;
        });
    }

    /**
     * توليد جلسات متكررة: من تاريخ البداية يُختار أول $count يوم يقع في الأيام الأسبوعية المحددة.
     * $data: course_id, subject_id, instructor_id?, weekdays[] (0=الأحد..6=السبت), from, count, starts_at, ends_at, type, location?
     *
     * @return Collection<int, TrainingSession>
     */
    public function generate(array $data, User $actor): Collection
    {
        $this->assertCan($actor, 'training_sessions.create');

        $weekdays = array_map('intval', (array) ($data['weekdays'] ?? []));
        $count = (int) ($data['count'] ?? 0);

        if ($weekdays === []) {
            throw new BusinessRuleException('اختر يومًا واحدًا على الأقل من أيام الأسبوع.');
        }

        if ($count < 1 || $count > 100) {
            throw new BusinessRuleException('عدد الجلسات يجب أن يكون بين 1 و100.');
        }

        $dates = [];
        $cursor = Carbon::parse($data['from'])->startOfDay();

        for ($guard = 0; count($dates) < $count && $guard < 1000; $guard++) {
            if (in_array($cursor->dayOfWeek, $weekdays, true)) {
                $dates[] = $cursor->toDateString();
            }

            $cursor = $cursor->copy()->addDay();
        }

        return DB::transaction(function () use ($dates, $data, $actor) {
            return collect($dates)->map(fn (string $date) => TrainingSession::create(
                $this->prepare(array_merge($data, ['session_date' => $date]), $actor, null)
                + ['status' => SessionStatus::Scheduled->value, 'created_by' => $actor->id]
            ));
        });
    }

    /** يتحقق من قواعد الجلسة ويرجع الحقول المعتمدة للحفظ. */
    private function prepare(array $data, User $actor, ?TrainingSession $existing): array
    {
        $course = Course::query()->find($data['course_id'] ?? null);

        if (! $course) {
            throw new BusinessRuleException('الدورة غير موجودة.');
        }

        $this->assertCenter($actor, (int) $course->center_id);

        if (! in_array($course->status, [CourseStatus::Upcoming, CourseStatus::Running], true)) {
            throw new BusinessRuleException('لا تُجدول جلسات لدورة حالتها «'.$course->status->label().'».');
        }

        $subjectId = (int) ($data['subject_id'] ?? 0);

        if (! $course->subjects()->whereKey($subjectId)->exists()) {
            throw new BusinessRuleException('المادة ليست من مواد هذه الدورة.');
        }

        $date = Carbon::parse($data['session_date'])->startOfDay();

        if ($date->lt($course->start_date->copy()->startOfDay()) || $date->gt($course->end_date->copy()->startOfDay())) {
            throw new BusinessRuleException('تاريخ الجلسة '.$date->format('Y-m-d').' خارج فترة الدورة ('.$course->start_date->format('Y-m-d').' إلى '.$course->end_date->format('Y-m-d').').');
        }

        $starts = $this->time($data['starts_at'] ?? null);
        $ends = $this->time($data['ends_at'] ?? null);

        if ($ends <= $starts) {
            throw new BusinessRuleException('وقت نهاية الجلسة يجب أن يكون بعد وقت بدايتها.');
        }

        $instructorId = filled($data['instructor_id'] ?? null) ? (int) $data['instructor_id'] : null;

        if ($instructorId !== null) {
            $instructor = Instructor::query()->find($instructorId);

            if (! $instructor || ! $instructor->is_active || ! $instructor->centers()->whereKey($course->center_id)->exists()) {
                throw new BusinessRuleException('المدرب غير فعّال أو لا يدرّس في مركز هذه الدورة.');
            }

            $assigned = CourseSubjectInstructor::query()
                ->where('course_id', $course->id)
                ->where('subject_id', $subjectId)
                ->where('instructor_id', $instructorId)
                ->exists();

            if (! $assigned) {
                throw new BusinessRuleException('المدرب غير مسنَد إلى هذه المادة في هذه الدورة. أسنده من صفحة الدورة أولًا.');
            }

            $clash = TrainingSession::query()
                ->where('instructor_id', $instructorId)
                ->whereDate('session_date', $date->toDateString())
                ->where('status', '!=', SessionStatus::Cancelled->value)
                ->where('starts_at', '<', $ends)
                ->where('ends_at', '>', $starts)
                ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
                ->exists();

            if ($clash) {
                throw new BusinessRuleException('للمدرب جلسة أخرى تتقاطع مع هذا الوقت في '.$date->format('Y-m-d').'.');
            }
        }

        return [
            'course_id' => $course->id,
            'subject_id' => $subjectId,
            'instructor_id' => $instructorId,
            'session_date' => $date->toDateString(),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'type' => $data['type'] instanceof \BackedEnum ? $data['type']->value : (string) $data['type'],
            'location' => filled($data['location'] ?? null) ? trim((string) $data['location']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ];
    }

    /** يوحّد صيغة الوقت إلى HH:MM:SS. */
    private function time(mixed $value): string
    {
        $text = (string) $value;

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $text, $m)) {
            throw new BusinessRuleException('صيغة الوقت غير صالحة.');
        }

        return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
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
