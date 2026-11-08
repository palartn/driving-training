<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    protected $table = 'training_sessions';

    // duration_minutes عمود مولَّد في قاعدة البيانات ولا يُكتب يدويًا.
    protected $fillable = [
        'course_id',
        'subject_id',
        'instructor_id',
        'session_date',
        'starts_at',
        'ends_at',
        'location',
        'type',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'type' => SessionType::class,
            'status' => SessionStatus::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** «09:00 - 10:30» */
    public function timeLabel(): string
    {
        return substr((string) $this->starts_at, 0, 5).' - '.substr((string) $this->ends_at, 0, 5);
    }
}
