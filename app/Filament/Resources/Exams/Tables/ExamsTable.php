<?php

namespace App\Filament\Resources\Exams\Tables;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['course.center', 'course.type']))
            ->columns([
                TextColumn::make('exam_date')
                    ->label('الموعد')
                    ->date()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge(),
                TextColumn::make('course.course_number')
                    ->label('الدورة')
                    ->searchable(),
                TextColumn::make('course.type.name')
                    ->label('نوع الدورة'),
                TextColumn::make('course.center.name')
                    ->label('المركز')
                    ->toggleable(),
                TextColumn::make('attempts_count')
                    ->label('المسجّلون')
                    ->counts('attempts'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('النوع')
                    ->options(ExamType::class),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ExamStatus::class),
                SelectFilter::make('course_id')
                    ->label('الدورة')
                    ->relationship('course', 'course_number'),
            ])
            ->recordActions([
                EditAction::make()->label('فتح'),
            ])
            ->defaultSort('exam_date', 'desc');
    }
}
