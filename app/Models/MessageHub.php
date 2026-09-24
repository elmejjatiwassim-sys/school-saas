<?php

namespace App\Models;

use App\Enums\MessageTargetType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageHub extends Model
{
    use HasFactory;

    protected $table = 'messages_hub';

    protected $fillable = [
        'school_id',
        'sender_id',
        'target_type',
        'target_id',
        'title',
        'message',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => MessageTargetType::class,
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'target_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'target_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'target_id');
    }

    protected function targetName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $targetType = $this->target_type instanceof MessageTargetType ? $this->target_type->value : $this->target_type;

                return match ($targetType) {
                    'classroom' => Classroom::find($this->target_id)?->name ?? "Class #{$this->target_id}",
                    'student' => Student::find($this->target_id)?->fullName ?? "Student #{$this->target_id}",
                    'guardian' => Guardian::find($this->target_id)?->fullName ?? "Guardian #{$this->target_id}",
                    default => "#{$this->target_id}",
                };
            }
        );
    }
}
