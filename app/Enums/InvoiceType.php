<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceType: string implements HasColor, HasLabel
{
    case Monthly = 'monthly';
    case AnnualPackage = 'annual_package';
    case Custom = 'custom';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Monthly => 'Monthly Tuition (واجب شهري)',
            self::AnnualPackage => 'Annual Package (عرض سنوي شامل)',
            self::Custom => 'Custom Invoice (فاتورة مخصصة)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Monthly => 'info',
            self::AnnualPackage => 'success',
            self::Custom => 'warning',
        };
    }
}
