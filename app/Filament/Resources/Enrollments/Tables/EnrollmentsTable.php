<?php

namespace App\Filament\Resources\Enrollments\Tables;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        $threshold = (int) config('training.unpaid_subjects_block_threshold', 2);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student', 'course.type']))
            ->columns([
                TextColumn::make('student.national_id')
                    ->label('رقم الهوية')
                    ->searchable(),
                TextColumn::make('student.full_name')
                    ->label('الطالب')
                    ->searchable(),
                TextColumn::make('course.course_number')
                    ->label('الدورة')
                    ->searchable(),
                TextColumn::make('course.type.name')
                    ->label('النوع'),
                TextColumn::make('enrolled_at')
                    ->label('تاريخ التسجيل')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('unpaid_subjects')
                    ->label('مواد غير مسددة')
                    ->state(fn (Enrollment $record) => $record->unpaidSubjectsCount())
                    ->badge()
                    ->color(fn ($state) => $state >= $threshold ? 'danger' : ($state > 0 ? 'warning' : 'success')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(EnrollmentStatus::class),
                SelectFilter::make('course_id')
                    ->label('الدورة')
                    ->relationship('course', 'course_number'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
