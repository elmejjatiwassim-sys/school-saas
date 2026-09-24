<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender',
        'message',
        'action_log_id',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function actionLog(): BelongsTo
    {
        return $this->belongsTo(AiActionLog::class, 'action_log_id');
    }
}
