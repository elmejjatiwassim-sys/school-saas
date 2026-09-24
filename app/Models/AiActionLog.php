<?php

namespace App\Models;

use App\Enums\AiActionLogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'user_id',
        'action_type',
        'payload',
        'status',
        'confirmed_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => AiActionLogStatus::class,
            'confirmed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): HasOne
    {
        return $this->hasOne(AiMessage::class, 'action_log_id');
    }
}
