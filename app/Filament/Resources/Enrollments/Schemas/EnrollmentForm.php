<?php

namespace App\Filament\Resources\Enrollments\Schemas;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class EnrollmentForm
{
    private static function studentLabel(Student $student): string
    {
        return $student->full_name.' — '.$student->national_id;
    }

    private static function courseLabel(Course $course): string
    {
        return $course->course_number.' — '.($course->type?->name ?? '');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات التسجيل')
                ->columns(2)
                ->schema([
                    Select::make('student_id')
                        ->label('الطالب')
                        ->placeholder('ابحث برقم الهوية أو الاسم أو الهاتف')
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

                            return Student::query()
                                ->when(auth()->user()?->center_id, fn ($q, $center) => $q->where('center_id', $center))
                                ->where(fn ($q) => $q
                                    ->where('national_id', 'like', $term)
                                    ->orWhere('full_name', 'like', $term)
                                    ->orWhere('phone', 'like', $term))
                                ->orderBy('full_name')
                                ->limit(30)
                                ->get()
                                ->mapWithKeys(fn (Student $s) => [$s->id => self::studentLabel($s)])
                                ->all();
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => ($student = Student::find($value))
                            ? self::studentLabel($student)
                            : null)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('course_id', null))
                        ->rule(fn (string $operation): Closure => function (string $attribute, $value, Closure $fail) use ($operation) {
                            if ($operation !== 'create' || blank($value)) {
                                return;
                            }

                            if (Enrollment::active()->where('student_id', $value)->exists()) {
                                $fail('الطالب مسجّل حاليًا في دورة جارية، ولا يمكن تسجيله في دورة أخرى قبل إغلاق تسجيله الحالي.');
                            }
                        }),
                    Select::make('course_id')
                        ->label('الدورة')
                        ->placeholder('اختر الطالب أولًا')
                        ->options(function (Get $get): array {
                            $student = Student::find($get('student_id'));

                            if (! $student) {
                                return [];
                            }

                            return Course::query()
                                ->with('type')
                                ->where('center_id', $student->center_id)
                                ->whereIn('status', ['upcoming', 'running'])
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn (Course $c) => [$c->id => self::courseLabel($c)])
                                ->all();
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => ($course = Course::with('type')->find($value))
                            ? self::courseLabel($course)
                            : null)
                        ->required()
                        ->searchable()
                        ->helperText('تظهر دورات مركز الطالب القادمة والجارية فقط.'),
                    DatePicker::make('enrolled_at')
                        ->label('تاريخ التسجيل')
                        ->default(today())
                        ->maxDate(today())
                        ->required()
                        ->helperText('بهذا التاريخ تُحدَّد أسعار المواد السارية.'),
                ]),
        ]);
    }
}
