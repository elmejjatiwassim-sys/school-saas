<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FeeInvoiceType: string implements HasColor, HasLabel
{
    case MonthlyFee = 'monthly_fee';
    case Transport = 'transport';
    case OpeningBalance = 'opening_balance';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MonthlyFee => 'Monthly Tuition (واجب شهري)',
            self::Transport => 'Transport Fee (نقل مدرسي)',
            self::OpeningBalance => 'Opening Balance (رصيد افتتاحي / متأخرات)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MonthlyFee => 'info',
            self::Transport => 'primary',
            self::OpeningBalance => 'danger',
        };
    }
}
