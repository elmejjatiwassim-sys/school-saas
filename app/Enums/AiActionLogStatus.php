<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AiActionLogStatus: string implements HasColor, HasLabel
{
    case PendingConfirmation = 'pending_confirmation';
    case Executed = 'executed';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PendingConfirmation => 'Pending Confirmation (في انتظار التأكيد)',
            self::Executed => 'Executed (تم التنفيذ بنجاح)',
            self::Cancelled => 'Cancelled (ملغى)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendingConfirmation => 'warning',
            self::Executed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
