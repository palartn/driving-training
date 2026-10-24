<?php

namespace App\Filament\Resources\RetakeFees\Schemas;

use App\Enums\ExamType;
use App\Models\RetakeFee;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class RetakeFeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('رسوم إعادة الاختبار')
                ->columns(2)
                ->schema([
                    Select::make('exam_type')
                        ->label('نوع الاختبار')
                        ->options(ExamType::class)
                        ->required(),
                    TextInput::make('amount')
                        ->label('المبلغ')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required()
                        ->suffix(config('training.currency_symbol', '₪')),
                    DatePicker::make('effective_from')
                        ->label('يسري من تاريخ')
                        ->default(today())
                        ->required()
                        ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                            if (blank($get('exam_type')) || blank($value)) {
                                return;
                            }

                            if (RetakeFee::where('exam_type', $get('exam_type'))->whereDate('effective_from', $value)->exists()) {
                                $fail('توجد رسوم لهذا النوع بهذا التاريخ.');
                            }
                        })
                        ->helperText('لا تُعدَّل الرسوم القديمة؛ يُضاف سجل جديد بتاريخ سريان.'),
                ]),
        ]);
    }
}
