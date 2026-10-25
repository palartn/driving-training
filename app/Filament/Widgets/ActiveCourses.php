<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CenterScopedQueries;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ActiveCourses extends TableWidget
{
    use CenterScopedQueries;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('courses.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('الدورات الجارية والقادمة')
            ->query(fn () => $this->courses()
                ->whereIn('status', ['running', 'upcoming'])
                ->with(['type', 'center'])
                ->withCount('enrollments')
                ->orderBy('start_date'))
            ->columns([
                TextColumn::make('course_number')
                    ->label('رقم الدورة'),
                TextColumn::make('type.name')
                    ->label('النوع'),
                TextColumn::make('center.name')
                    ->label('المركز')
                    ->visible(fn () => $this->centerId() === null),
                TextColumn::make('start_date')
                    ->label('البداية')
                    ->date(),
                TextColumn::make('end_date')
                    ->label('النهاية')
                    ->date(),
                TextColumn::make('enrollments_count')
                    ->label('المسجّلون'),
                TextColumn::make('seats')
                    ->label('المقاعد')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
            ])
            ->emptyStateHeading('لا توجد دورات جارية أو قادمة')
            ->paginated([5, 10]);
    }
}
