<?php

namespace App\Notifications;

use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AbsenceAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Student $student,
        public AttendanceRecord $attendanceRecord,
        public ?string $customMessage = null
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
        $sessionLabel = match ($this->attendanceRecord->session?->value ?? $this->attendanceRecord->session) {
            'morning' => 'صباحاً',
            'afternoon' => 'مساءً',
            'full_day' => 'طيلة اليوم',
            default => 'اليوم',
        };

        $defaultMessage = "نحيطكم علماً بغياب التلميذ(ة) {$this->student->fullName} بتاريخ {$this->attendanceRecord->date} ({$sessionLabel}). يرجى التواصل مع إدارة المؤسسة لتبرير الغياب.";

        return [
            'type' => 'absence_alert',
            'title' => 'تنبيه غياب تلميذ / Absence Notification',
            'message' => $this->customMessage ?? $defaultMessage,
            'student_id' => $this->student->id,
            'student_name' => $this->student->fullName,
            'date' => $this->attendanceRecord->date?->toDateString() ?? (string) $this->attendanceRecord->date,
            'session' => $this->attendanceRecord->session?->value ?? $this->attendanceRecord->session,
            'status' => $this->attendanceRecord->status?->value ?? $this->attendanceRecord->status,
        ];
    }
}
