<?php

namespace App\Filament\Resources\Enrollments\Pages;

use App\Enums\EnrollmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Services\EnrollmentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeEnrollment')
                ->label('إغلاق التسجيل (انسحاب / إلغاء)')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn () => auth()->user()->can('enrollments.close')
                    && $this->record->status === EnrollmentStatus::Active)
                ->schema([
                    Select::make('to')
                        ->label('نوع الإغلاق')
                        ->options([
                            EnrollmentStatus::Withdrawn->value => 'انسحاب',
                            EnrollmentStatus::Cancelled->value => 'إلغاء',
                        ])
                        ->required(),
                    Textarea::make('reason')
                        ->label('السبب')
                        ->required()
                        ->maxLength(255),
                ])
                ->requiresConfirmation()
                ->modalDescription('بعد الإغلاق يتحرر الطالب لتسجيل جديد في دورة أخرى، ولا يمكن التراجع.')
                ->action(function (array $data): void {
                    try {
                        app(EnrollmentService::class)->close(
                            $this->record,
                            EnrollmentStatus::from($data['to']),
                            $data['reason'],
                            auth()->user(),
                        );

                        Notification::make()->success()->title('تم إغلاق التسجيل')->send();

                        $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (BusinessRuleException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ];
    }
}
