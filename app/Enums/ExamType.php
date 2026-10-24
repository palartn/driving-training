<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExamType: string implements HasLabel
{
    case Theory = 'theory';
    case Practical = 'practical';

    public function label(): string
    {
        return match ($this) {
            self::Theory => 'نظري',
            self::Practical => 'عملي',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
