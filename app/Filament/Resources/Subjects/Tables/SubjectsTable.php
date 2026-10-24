<?php

namespace App\Filament\Resources\Subjects\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        $currency = ' '.config('training.currency_symbol', '₪');

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('المادة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currentTariff.total_price')
                    ->label('السعر الحالي')
                    ->suffix($currency)
                    ->placeholder('لا توجد تعريفة'),
                TextColumn::make('currentTariff.lectures_count')
                    ->label('عدد المحاضرات')
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label('فعّالة')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('فعّالة')
                    ->falseLabel('معطّلة'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
