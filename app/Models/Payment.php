<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'invoice_id',
        'amount',
        'payment_date',
        'payment_method',
        'cheque_number',
        'bank_name',
        'receipt_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Payment $payment): void {
            $payment->invoice?->recalculatePaidAmountAndStatus();
        });

        static::deleted(function (Payment $payment): void {
            $payment->invoice?->recalculatePaidAmountAndStatus();
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
