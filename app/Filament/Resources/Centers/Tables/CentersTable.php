<?php

namespace App\Filament\Resources\Centers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('الرمز')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('المركز')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('governorate.name')
                    ->label('المحافظة')
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->toggleable(),
                TextColumn::make('students_count')
                    ->label('عدد الطلاب')
                    ->counts('students'),
                IconColumn::make('is_active')
                    ->label('فعّال')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('governorate_id')
                    ->label('المحافظة')
                    ->relationship('governorate', 'name'),
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('فعّال')
                    ->falseLabel('معطّل'),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }
}
