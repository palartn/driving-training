<?php

namespace App\Models;

use App\Enums\PricingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectTariff extends Model
{
    // total_price عمود مولَّد في قاعدة البيانات ولا يُكتب يدويًا.
    protected $fillable = [
        'subject_id',
        'pricing_mode',
        'fixed_price',
        'lecture_price',
        'lectures_count',
        'effective_from',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'fixed_price' => 'decimal:2',
            'lecture_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
