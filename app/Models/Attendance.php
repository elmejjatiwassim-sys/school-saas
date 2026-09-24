<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\JustificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendances';

    protected $fillable = [
        'school_id',
        'student_id',
        'classroom_id',
        'academic_year_id',
        'timetable_id',
        'recorded_by_id',
        'date',
        'session',
        'status',
        'late_arrival_time',
        'remarks',
        'guardian_justification_note',
        'guardian_justification_attachment',
        'justification_status',
        'reviewed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'justification_status' => JustificationStatus::class,
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
