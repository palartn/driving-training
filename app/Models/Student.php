<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    // full_name عمود مولَّد في قاعدة البيانات ولا يُكتب يدويًا.
    protected $fillable = [
        'national_id',
        'first_name',
        'second_name',
        'third_name',
        'fourth_name',
        'phone',
        'phone_secondary',
        'address',
        'governorate_id',
        'center_id',
        'created_by',
    ];

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
