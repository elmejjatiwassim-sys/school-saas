<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'name',
        'default_amount',
        'is_recurring_monthly',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'is_recurring_monthly' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
