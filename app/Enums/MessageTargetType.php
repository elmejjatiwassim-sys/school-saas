<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MessageTargetType: string implements HasColor, HasLabel
{
    case Classroom = 'classroom';
    case Student = 'student';
    case Guardian = 'guardian';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Classroom => 'قسم كامل / Whole Class',
            self::Student => 'تلميذ معين / Specific Student',
            self::Guardian => 'ولي أمر / Guardian',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Classroom => 'info',
            self::Student => 'primary',
            self::Guardian => 'warning',
        };
    }
}
