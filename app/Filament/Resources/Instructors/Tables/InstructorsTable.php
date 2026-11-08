<?php

namespace App\Filament\Resources\Instructors\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InstructorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('centers'))
            ->columns([
                TextColumn::make('name')
                    ->label('المدرب')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('national_id')
                    ->label('رقم الهوية')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable(),
                TextColumn::make('specialization')
                    ->label('التخصص')
                    ->placeholder('-'),
                TextColumn::make('centers.name')
                    ->label('المراكز')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('فعّال')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('centers')
                    ->label('المركز')
                    ->relationship('centers', 'name')
                    ->visible(fn () => auth()->user()?->center_id === null),
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('فعّال')
                    ->falseLabel('معطّل'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }
}
