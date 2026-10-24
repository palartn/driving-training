<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PricingMode: string implements HasLabel
{
    case Fixed = 'fixed';
    case PerLecture = 'per_lecture';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'سعر ثابت للمادة',
            self::PerLecture => 'سعر المحاضرة × عدد المحاضرات',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
