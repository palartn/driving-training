<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FinancialStatus: string implements HasColor, HasLabel
{
    case Paid = 'paid';
    case Exempt = 'exempt';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'مسدد',
            self::Exempt => 'معافى',
            self::Unpaid => 'غير مسدد',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Exempt => 'info',
            self::Unpaid => 'danger',
        };
    }
}
