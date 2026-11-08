<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSubjectInstructor extends Model
{
    protected $table = 'course_subject_instructors';

    protected $fillable = ['course_id', 'subject_id', 'instructor_id'];

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
}
