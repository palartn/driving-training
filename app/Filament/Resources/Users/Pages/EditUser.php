<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($this->data['role'] ?? null) === 'general_manager') {
            $data['center_id'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // حقل الدور معطّل على حساب المستخدم نفسه فلا يُغيَّر.
        if (! $this->record->is(auth()->user()) && filled($this->data['role'] ?? null)) {
            $this->record->syncRoles([$this->data['role']]);
        }
    }
}
