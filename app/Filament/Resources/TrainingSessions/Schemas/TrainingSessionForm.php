<?php

namespace App\Filament\Resources\TrainingSessions\Schemas;

use App\Enums\SessionStatus;
use App\Enums\SessionType;
use App\Models\Course;
use App\Models\CourseSubjectInstructor;
use App\Models\Instructor;
use App\Models\Subject;
use App\Models\TrainingSession;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TrainingSessionForm
{
    private static function courseLabel(Course $course): string
    {
        return $course->course_number.' — '.($course->type?->name ?? '');
    }

    /** الدورة (قادمة/جارية في مركز المستخدم). تُستعمل أيضًا في توليد الجلسات. */
    public static function courseSelect(): Select
    {
        return Select::make('course_id')
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
            ->live()
            ->afterStateUpdated(function (Set $set): void {
                $set('subject_id', null);
                $set('instructor_id', null);
            })
            ->disabled(fn (?TrainingSession $record) => $record !== null && $record->attendanceRecords()->exists())
            ->dehydrated();
    }

    public static function subjectSelect(): Select
    {
        return Select::make('subject_id')
            ->label('المادة')
            ->options(function (Get $get): array {
                $course = Course::find($get('course_id'));

                return $course
                    ? $course->subjects()->orderBy('subjects.name')->pluck('subjects.name', 'subjects.id')->all()
                    : [];
            })
            ->getOptionLabelUsing(fn ($value): ?string => Subject::find($value)?->name)
            ->required()
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set('instructor_id', null))
            ->disabled(fn (?TrainingSession $record) => $record !== null && $record->attendanceRecords()->exists())
            ->dehydrated();
    }

    /** المدربون المسنَدون لهذه المادة في هذه الدورة. */
    public static function instructorSelect(): Select
    {
        return Select::make('instructor_id')
            ->label('المدرب')
            ->placeholder('اختياري')
            ->options(function (Get $get): array {
                if (blank($get('course_id')) || blank($get('subject_id'))) {
                    return [];
                }

                return CourseSubjectInstructor::query()
                    ->where('course_id', $get('course_id'))
                    ->where('subject_id', $get('subject_id'))
                    ->with('instructor')
                    ->get()
                    ->filter(fn (CourseSubjectInstructor $a) => $a->instructor->is_active)
                    ->mapWithKeys(fn (CourseSubjectInstructor $a) => [$a->instructor_id => $a->instructor->name])
                    ->all();
            })
            ->getOptionLabelUsing(fn ($value): ?string => Instructor::find($value)?->name)
            ->searchable()
            ->helperText('المدربون المسنَدون لهذه المادة في هذه الدورة. يُسنَد المدرب من صفحة الدورة.');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الجلسة')
                ->columns(2)
                ->schema([
                    self::courseSelect(),
                    self::subjectSelect(),
                    self::instructorSelect(),
                    Select::make('type')
                        ->label('نوع الجلسة')
                        ->options(SessionType::class)
                        ->required(),
                    DatePicker::make('session_date')
                        ->label('التاريخ')
                        ->required(),
                    TimePicker::make('starts_at')
                        ->label('من')
                        ->seconds(false)
                        ->required(),
                    TimePicker::make('ends_at')
                        ->label('إلى')
                        ->seconds(false)
                        ->required(),
                    TextInput::make('location')
                        ->label('المكان')
                        ->maxLength(150),
                    Select::make('status')
                        ->label('الحالة')
                        ->options(SessionStatus::class)
                        ->default(SessionStatus::Scheduled->value)
                        ->required()
                        ->visibleOn('edit')
                        ->helperText('تصبح «منعقدة» تلقائيًا عند تسجيل الحضور. ولا تُلغى جلسة سُجّل فيها حضور.'),
                    TextInput::make('notes')
                        ->label('ملاحظات')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
