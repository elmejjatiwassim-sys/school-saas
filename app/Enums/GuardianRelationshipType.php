<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelationshipType: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case LegalGuardian = 'legal_guardian';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::LegalGuardian => 'Legal Guardian',
        };
    }
}
