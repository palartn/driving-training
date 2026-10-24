<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CourseStatus: string implements HasColor, HasLabel
{
    case Upcoming = 'upcoming';
    case Running = 'running';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'قادمة',
            self::Running => 'جارية',
            self::Finished => 'منتهية',
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
            self::Upcoming => 'info',
            self::Running => 'success',
            self::Finished => 'gray',
            self::Cancelled => 'danger',
        };
    }
}
