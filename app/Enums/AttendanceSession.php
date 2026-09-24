<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceSession: string implements HasColor, HasLabel
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case FullDay = 'full_day';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Morning => 'Morning / صباح (08:30 - 12:00)',
            self::Afternoon => 'Afternoon / مساء (14:30 - 18:00)',
            self::FullDay => 'Full Day / يوم كامل',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Morning => 'info',
            self::Afternoon => 'warning',
            self::FullDay => 'success',
        };
    }
}
