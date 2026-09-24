<?php

namespace App\Filament\Resources\AiActionLogResource\Pages;

use App\Filament\Pages\AiCopilot;
use App\Filament\Resources\AiActionLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAiActionLogs extends ListRecords
{
    protected static string $resource = AiActionLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_copilot')
                ->label('Open AI Copilot (فتح المساعد الذكي)')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->url(fn (): string => AiCopilot::getUrl()),
        ];
    }
}
