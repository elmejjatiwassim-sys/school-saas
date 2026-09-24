<?php

namespace App\Filament\Resources\FeeTypeResource\Pages;

use App\Filament\Resources\FeeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFeeType extends EditRecord
{
    protected static string $resource = FeeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
