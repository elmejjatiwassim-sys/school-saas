<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid (غير مؤدى)',
            self::PartiallyPaid => 'Partially Paid (أداء جزئي)',
            self::Paid => 'Paid (مؤدى)',
            self::Overdue => 'Overdue (متأخر)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Overdue => 'gray',
        };
    }
}
