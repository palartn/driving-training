<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Enums\CourseStatus;
use App\Models\Course;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الدورة')
                ->columns(2)
                ->schema([
                    TextInput::make('course_number')
                        ->label('رقم الدورة')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit')
                        ->helperText('يُولَّد تلقائيًا (التسلسل/السنة) ولا يتغير بعد الإنشاء.'),
                    TextInput::make('course_sequence')
                        ->label('تسلسل الدورة (اختياري)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->visibleOn('create')
                        ->helperText('اتركه فارغًا ليُولَّد تلقائيًا. السنة تُؤخذ من تاريخ البداية.')
                        ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                            if (blank($value) || blank($get('start_date'))) {
                                return;
                            }
                            $year = Carbon::parse($get('start_date'))->year;
                            if (Course::where('course_year', $year)->where('course_sequence', (int) $value)->exists()) {
                                $fail("رقم الدورة {$value}/{$year} مستخدم مسبقًا.");
                            }
                        }),
                    Select::make('course_type_id')
                        ->label('نوع الدورة')
                        ->relationship(
                            'type',
                            'name',
                            modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                        )
                        ->required()
                        ->searchable()
                        ->preload(),
                    Select::make('center_id')
                        ->label('المركز')
                        ->relationship(
                            'center',
                            'name',
                            modifyQueryUsing: fn (Builder $query) => auth()->user()?->center_id
                                ? $query->whereKey(auth()->user()->center_id)
                                : $query->where('is_active', true),
                        )
                        ->required()
                        ->searchable()
                        ->preload()
                        ->default(fn () => auth()->user()?->center_id)
                        ->disabled(fn () => auth()->user()?->center_id !== null)
                        ->dehydrated(),
                    DatePicker::make('start_date')
                        ->label('تاريخ البداية')
                        ->required()
                        ->live(onBlur: true),
                    DatePicker::make('end_date')
                        ->label('تاريخ النهاية')
                        ->required()
                        ->afterOrEqual('start_date'),
                    TextInput::make('seats')
                        ->label('عدد المقاعد')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->helperText('للتنبيه فقط؛ لا يمنع التسجيل عند اكتماله.'),
                    Select::make('status')
                        ->label('حالة الدورة')
                        ->options(CourseStatus::class)
                        ->default(CourseStatus::Upcoming->value)
                        ->required(),
                ]),

            Section::make('مواد الدورة')
                ->schema([
                    Select::make('subjects')
                        ->label('المواد')
                        ->relationship(
                            'subjects',
                            'name',
                            modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (?Course $record) => $record !== null && $record->enrollments()->exists())
                        ->helperText('تُنشأ الوحدة المالية للطالب من هذه المواد وقت التسجيل، فلا تُعدَّل المواد بعد وجود تسجيلات.'),
                ]),
        ]);
    }
}
