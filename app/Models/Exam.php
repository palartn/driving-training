<?php

namespace App\Models;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = ['course_id', 'type', 'exam_date', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'status' => ExamStatus::class,
            'exam_date' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
