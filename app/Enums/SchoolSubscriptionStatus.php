<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SchoolSubscriptionStatus: string implements HasColor, HasLabel
{
    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Trial => 'Trial (فترة تجريبية)',
            self::Active => 'Active (مفعل)',
            self::Suspended => 'Suspended (معلق)',
            self::Cancelled => 'Cancelled (ملغى)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Trial => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
