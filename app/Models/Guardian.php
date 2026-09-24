<?php

namespace App\Models;

use App\Enums\GuardianRelationshipType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Guardian extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'school_id',
        'first_name',
        'last_name',
        'cin',
        'phone',
        'email',
        'address',
        'relationship_type',
    ];

    protected function casts(): array
    {
        return [
            'relationship_type' => GuardianRelationshipType::class,
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }
}
