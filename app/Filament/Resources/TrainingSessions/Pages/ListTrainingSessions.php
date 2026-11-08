<?php

namespace App\Filament\Resources\TrainingSessions\Pages;

use App\Enums\SessionType;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\TrainingSessions\Schemas\TrainingSessionForm;
use App\Filament\Resources\TrainingSessions\TrainingSessionResource;
use App\Services\SessionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTrainingSessions extends ListRecords
{
    protected static string $resource = TrainingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('توليد جلسات متكررة')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('info')
                ->visible(fn () => auth()->user()->can('training_sessions.create'))
                ->modalDescription('تُنشأ جلسات في الأيام الأسبوعية المحددة بدءًا من التاريخ المحدد حتى يكتمل العدد. وإن تعذّر أي تاريخ (خارج الدورة أو تعارض مدرب) فلا تُنشأ أي جلسة.')
                ->schema([
                    TrainingSessionForm::courseSelect(),
                    TrainingSessionForm::subjectSelect(),
                    TrainingSessionForm::instructorSelect(),
                    CheckboxList::make('weekdays')
                        ->label('أيام الأسبوع')
                        ->options([
                            6 => 'السبت',
                            0 => 'الأحد',
                            1 => 'الاثنين',
                            2 => 'الثلاثاء',
                            3 => 'الأربعاء',
                            4 => 'الخميس',
                            5 => 'الجمعة',
                        ])
                        ->columns(4)
                        ->required(),
                    DatePicker::make('from')
                        ->label('بدءًا من تاريخ')
                        ->default(today())
                        ->required(),
                    TextInput::make('count')
                        ->label('عدد الجلسات')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required()
                        ->helperText('يُقترح عدد محاضرات المادة المسجل في سعرها.'),
                    TimePicker::make('starts_at')
                        ->label('من')
                        ->seconds(false)
                        ->required(),
                    TimePicker::make('ends_at')
                        ->label('إلى')
                        ->seconds(false)
                        ->required(),
                    Select::make('type')
                        ->label('نوع الجلسات')
                        ->options(SessionType::class)
                        ->required(),
                    TextInput::make('location')
                        ->label('المكان')
                        ->maxLength(150),
                ])
                ->action(function (array $data): void {
                    try {
                        $created = app(SessionService::class)->generate($data, auth()->user());

                        Notification::make()->success()->title('تم توليد '.$created->count().' جلسة')->send();
                    } catch (BusinessRuleException $e) {
                        Notification::make()->danger()->title('تعذّر توليد الجلسات')->body($e->getMessage())->persistent()->send();
                    }
                }),
            CreateAction::make()->label('إضافة جلسة'),
        ];
    }
}
