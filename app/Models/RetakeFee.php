<?php

namespace App\Models;

use App\Enums\ExamType;
use Illuminate\Database\Eloquent\Model;

class RetakeFee extends Model
{
    protected $fillable = ['exam_type', 'amount', 'effective_from', 'created_by'];

    protected function casts(): array
    {
        return [
            'exam_type' => ExamType::class,
            'amount' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    /** رسوم الإعادة السارية لنوع اختبار في تاريخ معيّن. */
    public static function currentFor(ExamType $type, \DateTimeInterface|string $date): ?self
    {
        return static::query()
            ->where('exam_type', $type->value)
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();
    }
}
