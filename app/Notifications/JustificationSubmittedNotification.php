<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class JustificationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Attendance $attendance,
        public Student $student,
        public string $reason
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
            'type' => 'justification_submitted',
            'title' => 'تبرير غياب جديد / New Absence Justification',
            'message' => "تم تقديم تبرير غياب للتلميذ {$this->student->fullName}: {$this->reason}",
            'attendance_id' => $this->attendance->id,
            'student_id' => $this->student->id,
            'student_name' => $this->student->fullName,
            'reason' => $this->reason,
            'date' => $this->attendance->date?->toDateString() ?? (string) $this->attendance->date,
        ];
    }
}
