<?php

namespace App\Filament\Resources\CourseTypes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourseTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('النوع')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('courses_count')
                    ->label('عدد الدورات')
                    ->counts('courses'),
                IconColumn::make('is_active')
                    ->label('فعّال')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
