<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'fee_type_id',
        'amount',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    protected function subtotal(): Attribute
    {
        return Attribute::make(
            get: fn () => round((float) $this->amount * (int) $this->quantity, 2),
        );
    }
}
