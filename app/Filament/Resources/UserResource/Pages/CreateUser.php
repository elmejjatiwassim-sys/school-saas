<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Filament::hasTenancy() && Filament::getTenant()) {
            $data['school_id'] = Filament::getTenant()->getKey();
        }

        return $data;
    }
}
