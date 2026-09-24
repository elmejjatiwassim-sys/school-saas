<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Present => 'Present / حاضر',
            self::Absent => 'Absent / غائب',
            self::Late => 'Late / متأخر',
            self::Excused => 'Excused / مبرر',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Present => 'success',
            self::Absent => 'danger',
            self::Late => 'warning',
            self::Excused => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Present => 'heroicon-o-check-circle',
            self::Absent => 'heroicon-o-x-circle',
            self::Late => 'heroicon-o-clock',
            self::Excused => 'heroicon-o-document-check',
        };
    }
}
