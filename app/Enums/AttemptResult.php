<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttemptResult: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'معلّقة',
            self::Passed => 'ناجح',
            self::Failed => 'راسب',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Passed => 'success',
            self::Failed => 'danger',
        };
    }
}
