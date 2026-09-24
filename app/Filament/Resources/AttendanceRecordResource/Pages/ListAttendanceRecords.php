<?php

namespace App\Filament\Resources\AttendanceRecordResource\Pages;

use App\Filament\Pages\TakeAttendance;
use App\Filament\Resources\AttendanceRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceRecords extends ListRecords
{
    protected static string $resource = AttendanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('take_attendance')
                ->label('Take Attendance / تسجيل الغياب السريع')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->url(fn (): string => TakeAttendance::getUrl()),

            Actions\CreateAction::make(),
        ];
    }
}
