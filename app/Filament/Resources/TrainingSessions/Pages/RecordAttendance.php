<?php

namespace App\Filament\Resources\TrainingSessions\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\TrainingSessions\TrainingSessionResource;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Services\AttendanceService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class RecordAttendance extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TrainingSessionResource::class;

    protected string $view = 'filament.resources.training-sessions.pages.record-attendance';

    /** @var array<int, array{national_id: string, name: string, status: string, notes: string}> */
    public array $rows = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(
            auth()->user()->can('attendance.view') && static::getResource()::canView($this->getRecord()),
            403,
        );

        $this->loadRows();
    }

    public function getTitle(): string
    {
        return 'كشف حضور الجلسة';
    }

    public function statusOptions(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $s) => [$s->value => $s->label()])
            ->all();
    }

    /** سبب تعذّر التسجيل (أو null إن كان مسموحًا). */
    public function recordHint(): ?string
    {
        $session = $this->getRecord();

        if (! auth()->user()->can('attendance.create')) {
            return 'ليست لديك صلاحية تسجيل الحضور.';
        }

        if ($session->status === SessionStatus::Cancelled) {
            return 'الجلسة ملغاة.';
        }

        if ($session->session_date->gt(today())) {
            return 'لا يُسجَّل الحضور قبل يوم الجلسة ('.$session->session_date->format('Y-m-d').').';
        }

        return null;
    }

    private function loadRows(): void
    {
        $session = $this->getRecord();

        $existing = AttendanceRecord::query()
            ->where('training_session_id', $session->id)
            ->get()
            ->keyBy('enrollment_id');

        $enrollments = Enrollment::query()
            ->where('course_id', $session->course_id)
            ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $existing->keys()->all()))
            ->with('student')
            ->get()
            ->sortBy(fn (Enrollment $e) => $e->student->full_name);

        $this->rows = [];

        foreach ($enrollments as $enrollment) {
            $record = $existing->get($enrollment->id);

            $this->rows[$enrollment->id] = [
                'national_id' => $enrollment->student->national_id,
                'name' => $enrollment->student->full_name,
                'status' => $record?->status->value ?? '',
                'notes' => $record?->notes ?? '',
            ];
        }
    }

    /** يملأ الصفوف غير المحددة فقط بالحالة المعطاة. */
    public function markAll(string $status): void
    {
        if (AttendanceStatus::tryFrom($status) === null) {
            return;
        }

        foreach ($this->rows as $id => $row) {
            if (($row['status'] ?? '') === '') {
                $this->rows[$id]['status'] = $status;
            }
        }
    }

    public function save(): void
    {
        $entries = collect($this->rows)
            ->filter(fn (array $row) => filled($row['status'] ?? null))
            ->map(fn (array $row) => ['status' => $row['status'], 'notes' => $row['notes'] ?? null])
            ->all();

        try {
            $result = app(AttendanceService::class)->record($this->getRecord(), $entries, auth()->user());

            Notification::make()
                ->success()
                ->title('تم حفظ الحضور')
                ->body('عدد التغييرات: '.$result['changed'])
                ->send();

            $this->record = $this->resolveRecord($this->getRecord()->getKey());
            $this->loadRows();
        } catch (BusinessRuleException $e) {
            Notification::make()->danger()->title('تعذّر حفظ الحضور')->body($e->getMessage())->persistent()->send();
        }
    }
}
