<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Filament::hasTenancy() && Filament::getTenant()) {
            $data['school_id'] = Filament::getTenant()->getKey();
        }

        return $data;
    }
}
