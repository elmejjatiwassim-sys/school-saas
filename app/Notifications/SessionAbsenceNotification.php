<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\Timetable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SessionAbsenceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Student $student,
        public Timetable $timetable,
        public Attendance $attendance,
        public string $customMessage
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'session_absence',
            'title' => 'تسجيل غياب تلميذ / Absence Notification',
            'message' => $this->customMessage,
            'student_id' => $this->student->id,
            'student_name' => $this->student->fullName,
            'timetable_id' => $this->timetable->id,
            'subject_id' => $this->timetable->subject_id,
            'subject_name' => $this->timetable->subject?->name,
            'date' => $this->attendance->date?->toDateString() ?? (string) $this->attendance->date,
            'start_time' => substr((string) $this->timetable->start_time, 0, 5),
            'status' => 'absent',
            'attendance_id' => $this->attendance->id,
        ];
    }
}
