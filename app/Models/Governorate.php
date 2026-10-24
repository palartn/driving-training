<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Governorate extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function centers(): HasMany
    {
        return $this->hasMany(Center::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
