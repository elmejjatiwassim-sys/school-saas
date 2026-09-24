<?php

namespace App\Filament\Resources\TimetableResource\Pages;

use App\Filament\Resources\TimetableResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateTimetable extends CreateRecord
{
    protected static string $resource = TimetableResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Filament::hasTenancy() && Filament::getTenant()) {
            $data['school_id'] = Filament::getTenant()->getKey();
        }

        return $data;
    }
}
