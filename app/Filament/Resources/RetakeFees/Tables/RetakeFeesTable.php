<?php

namespace App\Filament\Resources\RetakeFees\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RetakeFeesTable
{
    public static function configure(Table $table): Table
    {
        $currency = ' '.config('training.currency_symbol', '₪');

        return $table
            ->columns([
                TextColumn::make('exam_type')
                    ->label('نوع الاختبار')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->suffix($currency)
                    ->weight('bold'),
                TextColumn::make('effective_from')
                    ->label('يسري من')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('effective_from', 'desc');
            
    }
}
