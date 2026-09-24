<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSubscriptionInvoice extends Model
{
    use HasFactory;

    protected $table = 'platform_subscriptions_invoices';

    protected $fillable = [
        'school_id',
        'billing_month',
        'active_students_count',
        'rate_applied',
        'total_amount',
        'stripe_invoice_id',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'active_students_count' => 'integer',
            'rate_applied' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
