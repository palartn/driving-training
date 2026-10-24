<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\FinancialStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    // active_student_id عمود مولَّد في قاعدة البيانات ولا يُكتب يدويًا.
    protected $fillable = [
        'student_id',
        'course_id',
        'enrolled_at',
        'status',
        'status_reason',
        'status_changed_by',
        'status_changed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'status' => EnrollmentStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Active->value);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollmentSubjects(): HasMany
    {
        return $this->hasMany(EnrollmentSubject::class);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function examOverrides(): HasMany
    {
        return $this->hasMany(ExamOverride::class);
    }

    /** عدد المواد غير المسددة في هذا التسجيل. */
    public function unpaidSubjectsCount(): int
    {
        return $this->enrollmentSubjects()
            ->with(['payment', 'exemption'])
            ->get()
            ->filter(fn (EnrollmentSubject $s) => $s->financialStatus() === FinancialStatus::Unpaid)
            ->count();
    }

    /** هل يُمنع الطالب من دخول الاختبار بسبب عدم السداد؟ (قبل النظر في الاستثناءات). */
    public function isBlockedFromExams(): bool
    {
        return $this->unpaidSubjectsCount() >= (int) config('training.unpaid_subjects_block_threshold', 2);
    }
}
