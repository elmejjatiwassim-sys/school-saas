<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'subject_id',
        'created_by',
        'title',
        'exam_date',
        'start_time',
        'end_time',
        'coefficient',
        'notes',
        'notify_guardians',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'coefficient' => 'decimal:2',
            'notify_guardians' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
