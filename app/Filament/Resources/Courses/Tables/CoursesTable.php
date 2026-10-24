<?php

namespace App\Filament\Resources\Courses\Tables;

use App\Enums\CourseStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course_number')
                    ->label('رقم الدورة')
                    ->searchable()
                    ->sortable(['course_year', 'course_sequence']),
                TextColumn::make('type.name')
                    ->label('النوع')
                    ->sortable(),
                TextColumn::make('center.name')
                    ->label('المركز')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('start_date')
                    ->label('البداية')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('النهاية')
                    ->date()
                    ->sortable(),
                TextColumn::make('enrollments_count')
                    ->label('المسجّلون')
                    ->counts('enrollments'),
                TextColumn::make('seats')
                    ->label('المقاعد')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(CourseStatus::class),
                SelectFilter::make('course_type_id')
                    ->label('النوع')
                    ->relationship('type', 'name'),
                SelectFilter::make('center_id')
                    ->label('المركز')
                    ->relationship('center', 'name')
                    ->visible(fn () => auth()->user()?->center_id === null),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
