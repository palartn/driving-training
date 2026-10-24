<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    // course_number عمود مولَّد في قاعدة البيانات (التسلسل/السنة) ولا يُكتب يدويًا.
    protected $fillable = [
        'course_type_id',
        'center_id',
        'course_sequence',
        'course_year',
        'start_date',
        'end_date',
        'seats',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => CourseStatus::class,
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, 'course_type_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'course_subjects')->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
