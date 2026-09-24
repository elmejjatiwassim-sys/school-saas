<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\JustificationStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\Timetable;
use App\Models\User;
use App\Notifications\JustificationSubmittedNotification;
use App\Notifications\SessionAbsenceNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;

class SessionAttendanceService
{
    /**
     * Resolves the matching timetables slot based on current day of week and current system time.
     */
    public function getCurrentActiveSession(User $user, ?Classroom $classroom = null): ?Timetable
    {
        $now = now();
        $currentDay = strtolower($now->format('l'));
        $currentTime = $now->format('H:i:s');

        $query = Timetable::query()
            ->where('day_of_week', $currentDay)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime);

        if ($user->school_id) {
            $query->where('school_id', $user->school_id);
        }

        if ($classroom) {
            $query->where('classroom_id', $classroom->id);
        }

        if ($this->isTeacher($user)) {
            $query->where('teacher_id', $user->id);
        }

        return $query->with(['classroom', 'subject', 'teacher', 'school'])->first();
    }

    /**
     * Record attendance for a student in a timetable slot.
     *
     *
     * @throws AuthorizationException
     */
    public function recordAttendance(
        int|Timetable $timetable_id,
        int|Student $student_id,
        string|AttendanceStatus $status,
        User $recorder_user,
        ?string $date = null
    ): Attendance {
        $timetable = $timetable_id instanceof Timetable
            ? $timetable_id
            : Timetable::with(['classroom', 'subject', 'school'])->findOrFail($timetable_id);

        $student = $student_id instanceof Student
            ? $student_id
            : Student::with(['guardian', 'user'])->findOrFail($student_id);

        if (! $this->canRecordAttendance($recorder_user, $timetable)) {
            throw new AuthorizationException('You are not authorized to record attendance for this session.');
        }

        $statusValue = $status instanceof AttendanceStatus ? $status->value : (string) $status;
        $lateArrivalTime = null;

        if ($statusValue === AttendanceStatus::Late->value || $statusValue === 'late') {
            $lateArrivalTime = now()->format('H:i');
        }

        $attendanceDate = $date ?? now()->toDateString();

        $academicYearId = $student->classroom?->academic_year_id
            ?? $timetable->classroom?->academic_year_id
            ?? AcademicYear::where('school_id', $timetable->school_id)->where('is_current', true)->value('id')
            ?? AcademicYear::where('school_id', $timetable->school_id)->value('id');

        $attendance = Attendance::updateOrCreate(
            [
                'student_id' => $student->id,
                'timetable_id' => $timetable->id,
                'date' => $attendanceDate,
            ],
            [
                'school_id' => $timetable->school_id,
                'classroom_id' => $timetable->classroom_id,
                'academic_year_id' => $academicYearId,
                'recorded_by_id' => $recorder_user->id,
                'status' => $statusValue,
                'late_arrival_time' => $lateArrivalTime,
                'justification_status' => JustificationStatus::Pending->value,
            ]
        );

        if ($statusValue === AttendanceStatus::Absent->value || $statusValue === 'absent') {
            $this->dispatchAbsenceNotification($student, $timetable, $attendance, $attendanceDate);
        }

        return $attendance->fresh();
    }

    /**
     * Submit guardian justification reason and optional attachment for the absent session.
     */
    public function submitJustification(
        int|Attendance $attendance_id,
        string $note,
        mixed $attachment = null
    ): Attendance {
        $attendance = $attendance_id instanceof Attendance
            ? $attendance_id
            : Attendance::findOrFail($attendance_id);

        $attachmentPath = null;
        if ($attachment instanceof UploadedFile) {
            $attachmentPath = $attachment->store('justifications', 'public');
        } elseif (is_string($attachment) && filled($attachment)) {
            $attachmentPath = $attachment;
        }

        $attendance->update([
            'guardian_justification_note' => $note,
            'guardian_justification_attachment' => $attachmentPath ?? $attendance->guardian_justification_attachment,
            'justification_status' => JustificationStatus::Pending->value,
        ]);

        $fresh = $attendance->fresh(['student', 'timetable.subject', 'school']);
        $student = $fresh->student;
        $subjectName = $fresh->timetable?->subject?->name ?? 'المادة';

        $supervisors = User::where('school_id', $fresh->school_id)
            ->where(function ($q) {
                $q->whereIn('role', ['supervisor', 'general_supervisor', 'surveillant_general', 'admin'])
                    ->orWhere('is_super_admin', true);
            })
            ->get();

        if ($student) {
            $notification = new JustificationSubmittedNotification($fresh, $student, $note);
            foreach ($supervisors as $supervisor) {
                $supervisor->notify($notification);

                FilamentNotification::make()
                    ->title('تبرير غياب جديد')
                    ->body("تم تقديم تبرير غياب للتلميذ {$student->fullName} في حصة {$subjectName}.")
                    ->warning()
                    ->sendToDatabase($supervisor);
            }
        }

        return $fresh;
    }

    /**
     * Action "قبول التبرير / Approve": switches status to 'excused' and justification_status to 'approved'.
     *
     * @throws AuthorizationException
     */
    public function approveJustification(int|Attendance $attendance_id, User $reviewer): Attendance
    {
        if (! $this->canApproveJustification($reviewer)) {
            throw new AuthorizationException('Only general supervisors and school admins can approve justifications.');
        }

        $attendance = $attendance_id instanceof Attendance
            ? $attendance_id
            : Attendance::findOrFail($attendance_id);

        $attendance->update([
            'status' => AttendanceStatus::Excused->value,
            'justification_status' => JustificationStatus::Approved->value,
            'reviewed_by_id' => $reviewer->id,
        ]);

        return $attendance->fresh();
    }

    /**
     * Action "تعديل إلى متأخر / Mark Late": updates status to 'late' with arrival timestamp.
     *
     * @throws AuthorizationException
     */
    public function markAsLate(int|Attendance $attendance_id, User $reviewer, ?string $lateArrivalTime = null): Attendance
    {
        if (! $this->canApproveJustification($reviewer)) {
            throw new AuthorizationException('Only general supervisors and school admins can modify attendance status.');
        }

        $attendance = $attendance_id instanceof Attendance
            ? $attendance_id
            : Attendance::findOrFail($attendance_id);

        $arrivalTime = $lateArrivalTime ?? now()->format('H:i');

        $attendance->update([
            'status' => AttendanceStatus::Late->value,
            'late_arrival_time' => $arrivalTime,
            'justification_status' => JustificationStatus::Approved->value,
            'reviewed_by_id' => $reviewer->id,
        ]);

        return $attendance->fresh();
    }

    /**
     * Reject justification.
     *
     * @throws AuthorizationException
     */
    public function rejectJustification(int|Attendance $attendance_id, User $reviewer, ?string $reason = null): Attendance
    {
        if (! $this->canApproveJustification($reviewer)) {
            throw new AuthorizationException('Only general supervisors and school admins can reject justifications.');
        }

        $attendance = $attendance_id instanceof Attendance
            ? $attendance_id
            : Attendance::findOrFail($attendance_id);

        $remarks = $reason
            ? ($attendance->remarks ? $attendance->remarks.' | '.$reason : $reason)
            : $attendance->remarks;

        $attendance->update([
            'justification_status' => JustificationStatus::Rejected->value,
            'reviewed_by_id' => $reviewer->id,
            'remarks' => $remarks,
        ]);

        return $attendance->fresh();
    }

    /**
     * Dispatch in-app notification to guardian for absent student.
     */
    protected function dispatchAbsenceNotification(Student $student, Timetable $timetable, Attendance $attendance, string $date): void
    {
        $startTime = substr((string) $timetable->start_time, 0, 5);
        $subjectName = $timetable->subject?->name ?? 'المادة';
        $studentName = $student->fullName;

        $template = 'تسجيل غياب التلميذ :student في حصة :subject بتاريخ :date الساعة :start_time.';
        $message = str_replace(
            [':student', ':subject', ':date', ':start_time'],
            [$studentName, $subjectName, $date, $startTime],
            $template
        );

        $notification = new SessionAbsenceNotification($student, $timetable, $attendance, $message);

        $guardian = $student->guardian;
        if ($guardian) {
            $guardian->notify($notification);

            if ($guardian->email) {
                $guardianUser = User::where('school_id', $timetable->school_id)
                    ->where('email', $guardian->email)
                    ->first();

                if ($guardianUser) {
                    $guardianUser->notify($notification);

                    FilamentNotification::make()
                        ->title('تسجيل غياب تلميذ')
                        ->body($message)
                        ->danger()
                        ->sendToDatabase($guardianUser);
                }
            }
        }

        if ($student->user) {
            $student->user->notify($notification);
        }
    }

    public function isTeacher(User $user): bool
    {
        return $user->role === 'teacher';
    }

    public function isSupervisor(User $user): bool
    {
        return in_array($user->role, ['supervisor', 'general_supervisor', 'surveillant_general']);
    }

    public function isAdmin(User $user): bool
    {
        return in_array($user->role, ['admin', 'super_admin']) || (bool) $user->is_super_admin;
    }

    public function canRecordAttendance(User $user, Timetable $timetable): bool
    {
        if ($this->isAdmin($user) || $this->isSupervisor($user)) {
            return true;
        }

        if ($this->isTeacher($user)) {
            return (int) $timetable->teacher_id === (int) $user->id;
        }

        return false;
    }

    public function canApproveJustification(User $user): bool
    {
        return $this->isAdmin($user) || $this->isSupervisor($user);
    }
}
