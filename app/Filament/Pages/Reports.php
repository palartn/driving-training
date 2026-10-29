<?php

namespace App\Filament\Pages;

use App\Models\Center;
use App\Models\Course;
use App\Models\Exam;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static ?string $navigationLabel = 'التقارير';

    protected static ?string $title = 'التقارير والطباعة';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public function isCenterBound(): bool
    {
        return auth()->user()?->center_id !== null;
    }

    public function courseOptions(): array
    {
        return Course::query()
            ->with('type')
            ->when(auth()->user()?->center_id, fn (Builder $q, $center) => $q->where('center_id', $center))
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->mapWithKeys(fn (Course $c) => [$c->id => $c->course_number.' — '.($c->type?->name ?? '')])
            ->all();
    }

    public function examOptions(): array
    {
        return Exam::query()
            ->with('course')
            ->when(auth()->user()?->center_id, fn (Builder $q, $center) => $q->whereHas('course', fn (Builder $c) => $c->where('center_id', $center)))
            ->orderByDesc('exam_date')
            ->limit(300)
            ->get()
            ->mapWithKeys(fn (Exam $e) => [$e->id => $e->exam_date->format('Y-m-d').' — '.$e->type->label().' — '.$e->course->course_number])
            ->all();
    }

    public function centerOptions(): array
    {
        return Center::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
