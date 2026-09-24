<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case BankTransfer = 'bank_transfer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'Cash (نقداً)',
            self::Cheque => 'Cheque (شيك)',
            self::BankTransfer => 'Bank Transfer (تحويل بنكي)',
        };
    }
}
