<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Subject extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(SubjectTariff::class);
    }

    /** التعريفة السارية اليوم: الأحدث بين ما بدأ سريانه حتى اليوم. */
    public function currentTariff(): HasOne
    {
        return $this->hasOne(SubjectTariff::class)->ofMany(
            ['effective_from' => 'max'],
            fn ($query) => $query->whereDate('effective_from', '<=', today()),
        );
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_subjects')->withTimestamps();
    }

    /** التعريفة السارية في تاريخ معيّن (الأحدث بين ما سرى حتى ذلك التاريخ). */
    public function tariffOn(\DateTimeInterface|string $date): ?SubjectTariff
    {
        return $this->tariffs()
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }
}
