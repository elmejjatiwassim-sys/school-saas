<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Transferred = 'transferred';
    case Graduated = 'graduated';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Transferred => 'Transferred',
            self::Graduated => 'Graduated',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Transferred => 'warning',
            self::Graduated => 'info',
        };
    }
}
