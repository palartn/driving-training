<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // المدير العام بلا مركز دائمًا.
        if (($this->data['role'] ?? null) === 'general_manager') {
            $data['center_id'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->data['role']]);
    }
}
