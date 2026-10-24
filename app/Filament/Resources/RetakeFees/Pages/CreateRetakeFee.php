<?php

namespace App\Filament\Resources\RetakeFees\Pages;

use App\Filament\Resources\RetakeFees\RetakeFeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRetakeFee extends CreateRecord
{
    protected static string $resource = RetakeFeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
