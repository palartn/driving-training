<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnrollmentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'جارٍ',
            self::Completed => 'مكتمل',
            self::Withdrawn => 'منسحب',
            self::Cancelled => 'ملغى',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Completed => 'success',
            self::Withdrawn => 'warning',
            self::Cancelled => 'danger',
        };
    }
}
