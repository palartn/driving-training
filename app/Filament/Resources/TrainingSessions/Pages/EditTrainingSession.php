<?php

namespace App\Filament\Resources\TrainingSessions\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\TrainingSessions\TrainingSessionResource;
use App\Services\SessionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTrainingSession extends EditRecord
{
    protected static string $resource = TrainingSessionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SessionService::class)->update($record, $data, auth()->user());
        } catch (BusinessRuleException $e) {
            Notification::make()->danger()->title('تعذّر حفظ الجلسة')->body($e->getMessage())->persistent()->send();

            $this->halt();
        }
    }
}
