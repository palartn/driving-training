<?php

namespace App\Filament\Resources\Instructors\Pages;

use App\Filament\Resources\Instructors\InstructorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInstructor extends CreateRecord
{
    protected static string $resource = InstructorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    /** المستخدم المرتبط بمركز لا يرى حقل المراكز، فيُربط المدرب بمركزه تلقائيًا. */
    protected function afterCreate(): void
    {
        $center = auth()->user()?->center_id;

        if ($center !== null) {
            $this->record->centers()->syncWithoutDetaching([$center]);
        }
    }
}
