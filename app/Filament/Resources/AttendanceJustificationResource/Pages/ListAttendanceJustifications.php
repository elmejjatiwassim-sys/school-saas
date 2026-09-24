<?php

namespace App\Filament\Resources\AttendanceJustificationResource\Pages;

use App\Enums\JustificationStatus;
use App\Filament\Resources\AttendanceJustificationResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAttendanceJustifications extends ListRecords
{
    protected static string $resource = AttendanceJustificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending / قيد المراجعة')
                ->badge(fn () => $this->getModel()::where('justification_status', JustificationStatus::Pending)->whereNotNull('guardian_justification_note')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('justification_status', JustificationStatus::Pending)),

            'all' => Tab::make('All Justifications / كل التبريرات'),

            'approved' => Tab::make('Approved / مقبول')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('justification_status', JustificationStatus::Approved)),

            'rejected' => Tab::make('Rejected / مرفوض')
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('justification_status', JustificationStatus::Rejected)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
