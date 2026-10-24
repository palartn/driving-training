<?php

namespace App\Filament\Resources\Exams\Schemas;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Course;
use App\Models\Exam;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExamForm
{
    private static function courseLabel(Course $course): string
    {
        return $course->course_number.' — '.($course->type?->name ?? '');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الاختبار')
                ->columns(2)
                ->schema([
                    Select::make('course_id')
                        ->label('الدورة')
                        ->options(fn (): array => Course::query()
                            ->with('type')
                            ->when(auth()->user()?->center_id, fn ($q, $center) => $q->where('center_id', $center))
                            ->whereIn('status', ['upcoming', 'running'])
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(fn (Course $c) => [$c->id => self::courseLabel($c)])
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => ($course = Course::with('type')->find($value))
                            ? self::courseLabel($course)
                            : null)
                        ->required()
                        ->searchable()
                        ->disabled(fn (?Exam $record) => $record !== null && $record->attempts()->exists())
                        ->dehydrated()
                        ->helperText('تُقفل الدورة والنوع بعد تسجيل أول طالب في الاختبار.'),
                    Select::make('type')
                        ->label('نوع الاختبار')
                        ->options(ExamType::class)
                        ->required()
                        ->disabled(fn (?Exam $record) => $record !== null && $record->attempts()->exists())
                        ->dehydrated(),
                    DatePicker::make('exam_date')
                        ->label('موعد الاختبار')
                        ->required()
                        ->minDate(fn (string $operation) => $operation === 'create' ? today() : null),
                    Select::make('status')
                        ->label('الحالة')
                        ->options(ExamStatus::class)
                        ->default(ExamStatus::Scheduled->value)
                        ->required(),
                    TextInput::make('notes')
                        ->label('ملاحظات')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
