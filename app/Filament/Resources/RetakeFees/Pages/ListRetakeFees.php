<?php

namespace App\Filament\Resources\RetakeFees\Pages;

use App\Filament\Resources\RetakeFees\RetakeFeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRetakeFees extends ListRecords
{
    protected static string $resource = RetakeFeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة رسوم جديدة'),
        ];
    }
}
