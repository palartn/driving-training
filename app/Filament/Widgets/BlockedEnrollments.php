<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CenterScopedQueries;
use App\Models\Enrollment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class BlockedEnrollments extends TableWidget
{
    use CenterScopedQueries;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('enrollments.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('طلاب محظورون من الاختبار بسبب مواد غير مسددة')
            ->query(fn () => $this->blockedEnrollments()->with(['student', 'course']))
            ->columns([
                TextColumn::make('student.national_id')
                    ->label('رقم الهوية'),
                TextColumn::make('student.full_name')
                    ->label('الطالب'),
                TextColumn::make('student.phone')
                    ->label('الهاتف'),
                TextColumn::make('course.course_number')
                    ->label('الدورة'),
                TextColumn::make('unpaid')
                    ->label('مواد غير مسددة')
                    ->state(fn (Enrollment $record) => $record->unpaidSubjectsCount())
                    ->badge()
                    ->color('danger'),
            ])
            ->emptyStateHeading('لا يوجد طلاب محظورون حاليًا')
            ->paginated([5, 10]);
    }
}
