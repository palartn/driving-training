<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'training_session_id',
        'enrollment_id',
        'status',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
