<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CenterScopedQueries;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingExams extends TableWidget
{
    use CenterScopedQueries;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('exams.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('الاختبارات القادمة (30 يومًا)')
            ->query(fn () => $this->exams()
                ->where('status', 'scheduled')
                ->whereDate('exam_date', '>=', today())
                ->whereDate('exam_date', '<=', today()->addDays(30))
                ->with(['course.type', 'course.center'])
                ->withCount('attempts')
                ->orderBy('exam_date'))
            ->columns([
                TextColumn::make('exam_date')
                    ->label('الموعد')
                    ->date(),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge(),
                TextColumn::make('course.course_number')
                    ->label('الدورة'),
                TextColumn::make('course.center.name')
                    ->label('المركز')
                    ->visible(fn () => $this->centerId() === null),
                TextColumn::make('attempts_count')
                    ->label('المسجّلون'),
            ])
            ->emptyStateHeading('لا توجد اختبارات مجدولة خلال 30 يومًا')
            ->paginated([5, 10]);
    }
}
