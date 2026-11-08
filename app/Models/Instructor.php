<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
    protected $fillable = [
        'national_id',
        'name',
        'phone',
        'specialization',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** المراكز التي يدرّس فيها المدرب. */
    public function centers(): BelongsToMany
    {
        return $this->belongsToMany(Center::class, 'instructor_center')->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CourseSubjectInstructor::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
}
