<?php

namespace App\Filament\Resources\TrainingSessions\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\TrainingSessions\TrainingSessionResource;
use App\Services\SessionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTrainingSession extends CreateRecord
{
    protected static string $resource = TrainingSessionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SessionService::class)->create($data, auth()->user());
        } catch (BusinessRuleException $e) {
            Notification::make()->danger()->title('تعذّر إنشاء الجلسة')->body($e->getMessage())->persistent()->send();

            $this->halt();
        }
    }
}
