<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SessionStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Held = 'held';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدولة',
            self::Held => 'منعقدة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Held => 'success',
            self::Cancelled => 'danger',
        };
    }
}
