<?php

namespace App\Models;

use App\Enums\AttemptResult;
use App\Enums\ExamType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamAttempt extends Model
{
    protected $fillable = [
        'exam_id',
        'enrollment_id',
        'exam_type',
        'attempt_no',
        'result',
        'retake_fee_amount',
        'entered_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'exam_type' => ExamType::class,
            'result' => AttemptResult::class,
            'retake_fee_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
