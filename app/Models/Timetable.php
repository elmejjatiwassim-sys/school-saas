<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'classroom_id',
        'subject_id',
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room_name',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Timetable $timetable): void {
            if (is_string($timetable->start_time) && strlen($timetable->start_time) === 5) {
                $timetable->start_time .= ':00';
            }
            if (is_string($timetable->end_time) && strlen($timetable->end_time) === 5) {
                $timetable->end_time .= ':00';
            }

            $startTime = is_string($timetable->start_time) ? substr($timetable->start_time, 0, 5) : $timetable->start_time;
            $endTime = is_string($timetable->end_time) ? substr($timetable->end_time, 0, 5) : $timetable->end_time;

            if ($startTime >= $endTime) {
                throw ValidationException::withMessages([
                    'end_time' => 'End time must be after start time (وقت الانتهاء يجب أن يكون بعد وقت البدء).',
                ]);
            }

            if (static::hasTeacherConflict(
                (int) $timetable->teacher_id,
                $timetable->day_of_week,
                $startTime,
                $endTime,
                $timetable->id
            )) {
                throw ValidationException::withMessages([
                    'teacher_id' => 'This teacher is already scheduled for another class during this time slot (هذا الأستاذ مبرمج لحصة أخرى في نفس هذا التوقيت).',
                ]);
            }

            if (static::hasClassroomConflict(
                (int) $timetable->classroom_id,
                $timetable->day_of_week,
                $startTime,
                $endTime,
                $timetable->id
            )) {
                throw ValidationException::withMessages([
                    'classroom_id' => 'This classroom already has a session scheduled during this time slot (هذا الفصل الدراسي مبرمج لحصة أخرى في نفس هذا التوقيت).',
                ]);
            }

            if (filled($timetable->room_name) && static::hasRoomConflict(
                $timetable->room_name,
                $timetable->day_of_week,
                $startTime,
                $endTime,
                $timetable->id
            )) {
                throw ValidationException::withMessages([
                    'room_name' => 'This room is already reserved during this time slot (هذه القاعة محجوزة مسبقًا في نفس هذا التوقيت).',
                ]);
            }
        });
    }

    public static function hasTeacherConflict(int $teacherId, string|DayOfWeek $day, string $start, string $end, ?int $excludeId = null): bool
    {
        $dayVal = $day instanceof DayOfWeek ? $day->value : $day;
        $start = strlen($start) === 5 ? $start.':00' : $start;
        $end = strlen($end) === 5 ? $end.':00' : $end;

        return static::where('teacher_id', $teacherId)
            ->where('day_of_week', $dayVal)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }

    public static function hasClassroomConflict(int $classroomId, string|DayOfWeek $day, string $start, string $end, ?int $excludeId = null): bool
    {
        $dayVal = $day instanceof DayOfWeek ? $day->value : $day;
        $start = strlen($start) === 5 ? $start.':00' : $start;
        $end = strlen($end) === 5 ? $end.':00' : $end;

        return static::where('classroom_id', $classroomId)
            ->where('day_of_week', $dayVal)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }

    public static function hasRoomConflict(string $roomName, string|DayOfWeek $day, string $start, string $end, ?int $excludeId = null): bool
    {
        if (blank($roomName)) {
            return false;
        }

        $dayVal = $day instanceof DayOfWeek ? $day->value : $day;
        $start = strlen($start) === 5 ? $start.':00' : $start;
        $end = strlen($end) === 5 ? $end.':00' : $end;

        return static::where('room_name', $roomName)
            ->where('day_of_week', $dayVal)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
