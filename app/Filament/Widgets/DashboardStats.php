<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CenterScopedQueries;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends StatsOverviewWidget
{
    use CenterScopedQueries;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('students.view') ?? false;
    }

    protected function getStats(): array
    {
        $blocked = $this->blockedEnrollments()->count();
        $unpaid = $this->unpaidUnits()->count();

        return [
            Stat::make('الطلاب', $this->students()->count())
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make('الدورات الجارية', $this->courses()->where('status', 'running')->count())
                ->icon(Heroicon::OutlinedAcademicCap)
                ->color('success'),
            Stat::make('الدورات القادمة', $this->courses()->where('status', 'upcoming')->count())
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('info'),
            Stat::make('التسجيلات الجارية', $this->activeEnrollments()->count())
                ->icon(Heroicon::OutlinedClipboardDocumentCheck),
            Stat::make(
                'اختبارات خلال 7 أيام',
                $this->exams()
                    ->where('status', 'scheduled')
                    ->whereDate('exam_date', '>=', today())
                    ->whereDate('exam_date', '<=', today()->addDays(7))
                    ->count()
            )
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('info'),
            Stat::make('مواد غير مسددة', $unpaid)
                ->description($blocked > 0 ? $blocked.' طالب محظور من الاختبار' : 'لا يوجد محظورون')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color($blocked > 0 ? 'danger' : ($unpaid > 0 ? 'warning' : 'success')),
        ];
    }
}
