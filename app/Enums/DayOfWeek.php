<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DayOfWeek: string implements HasColor, HasLabel
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Monday => 'Monday (الإثنين)',
            self::Tuesday => 'Tuesday (الثلاثاء)',
            self::Wednesday => 'Wednesday (الأربعاء)',
            self::Thursday => 'Thursday (الخميس)',
            self::Friday => 'Friday (الجمعة)',
            self::Saturday => 'Saturday (السبت)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Monday => 'primary',
            self::Tuesday => 'info',
            self::Wednesday => 'warning',
            self::Thursday => 'success',
            self::Friday => 'danger',
            self::Saturday => 'gray',
        };
    }

    public function getOrder(): int
    {
        return match ($this) {
            self::Monday => 1,
            self::Tuesday => 2,
            self::Wednesday => 3,
            self::Thursday => 4,
            self::Friday => 5,
            self::Saturday => 6,
        };
    }
}
