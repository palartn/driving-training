<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Absent => 'غائب',
            self::Excused => 'غياب بعذر',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Excused => 'warning',
        };
    }
}
