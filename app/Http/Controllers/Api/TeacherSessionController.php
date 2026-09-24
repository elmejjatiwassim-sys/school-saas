<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\JustificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Timetable;
use App\Services\SessionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherSessionController extends Controller
{
    public function __construct(
        protected SessionAttendanceService $attendanceService
    ) {}

    /**
     * Get active timetable session for current authenticated teacher.
     */
    public function activeSession(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeSlot = $this->attendanceService->getCurrentActiveSession($user);

        if (! $activeSlot) {
            return response()->json([
                'success' => true,
                'has_active_session' => false,
                'message' => 'لا توجد حصة دراسية نشطة حالياً (No active session at this time).',
            ]);
        }

        $today = now()->toDateString();

        // Get existing attendance status for today's session if already recorded
        $existingRecords = Attendance::where('timetable_id', $activeSlot->id)
            ->where('date', $today)
            ->get()
            ->keyBy('student_id');

        $students = Student::where('classroom_id', $activeSlot->classroom_id)
            ->where('school_id', $activeSlot->school_id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Student $student) use ($existingRecords) {
                $existing = $existingRecords->get($student->id);
                $status = $existing ? ($existing->status instanceof AttendanceStatus ? $existing->status->value : (string) $existing->status) : 'present';

                return [
                    'id' => $student->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'full_name' => $student->fullName,
                    'photo' => $student->photo ? asset('storage/'.$student->photo) : null,
                    'gender' => $student->gender instanceof Gender ? $student->gender->value : (string) $student->gender,
                    'registration_number' => $student->registration_number,
                    'current_status' => $status,
                ];
            });

        return response()->json([
            'success' => true,
            'has_active_session' => true,
            'session' => [
                'timetable_id' => $activeSlot->id,
                'subject_name' => $activeSlot->subject?->name ?? 'المادة',
                'classroom_name' => $activeSlot->classroom?->name ?? 'القسم',
                'classroom_id' => $activeSlot->classroom_id,
                'start_time' => substr((string) $activeSlot->start_time, 0, 5),
                'end_time' => substr((string) $activeSlot->end_time, 0, 5),
                'day_of_week' => $activeSlot->day_of_week,
            ],
            'students' => $students->values()->all(),
        ]);
    }

    /**
     * Record session attendances in bulk for teacher.
     */
    public function bulkRecord(Request $request): JsonResponse
    {
        $request->validate([
            'timetable_id' => 'required|integer|exists:timetables,id',
            'date' => 'nullable|date_format:Y-m-d',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|integer|exists:students,id',
            'records.*.status' => 'required|string|in:present,absent,late,excused',
        ]);

        $timetable = Timetable::with(['classroom', 'subject', 'school'])->findOrFail($request->timetable_id);
        $user = $request->user();
        $date = $request->input('date', now()->toDateString());

        $records = $request->input('records');
        $savedAttendances = [];

        foreach ($records as $record) {
            $attendance = $this->attendanceService->recordAttendance(
                $timetable,
                (int) $record['student_id'],
                $record['status'],
                $user,
                $date
            );

            $savedAttendances[] = [
                'id' => $attendance->id,
                'student_id' => $attendance->student_id,
                'status' => $attendance->status instanceof AttendanceStatus ? $attendance->status->value : (string) $attendance->status,
                'late_arrival_time' => $attendance->late_arrival_time,
                'justification_status' => $attendance->justification_status instanceof JustificationStatus ? $attendance->justification_status->value : (string) $attendance->justification_status,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل وتأكيد الحضور بنجاح وإرسال الإشعارات (Session attendance recorded and notifications dispatched).',
            'recorded_count' => count($savedAttendances),
            'records' => $savedAttendances,
        ], 200);
    }
}
