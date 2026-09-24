<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\JustificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\SessionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianStudentController extends Controller
{
    public function __construct(
        protected SessionAttendanceService $attendanceService
    ) {}

    /**
     * List all students/children linked to the authenticated guardian.
     */
    public function children(Request $request): JsonResponse
    {
        $user = $request->user();
        $students = collect();

        // 1. Check if user is linked to a guardian record
        $guardian = $user->getGuardianRecord();

        if ($guardian) {
            $students = $guardian->students()
                ->with(['classroom.gradeLevel', 'school'])
                ->orderBy('first_name')
                ->get();
        } elseif ($user->student_id) {
            $student = Student::with(['classroom.gradeLevel', 'school'])->find($user->student_id);
            if ($student) {
                $students = collect([$student]);
            }
        } elseif ($user->isAdmin() || $user->isSupervisor()) {
            // For testing/admin debugging preview, get school students
            $students = Student::where('school_id', $user->school_id)
                ->with(['classroom.gradeLevel', 'school'])
                ->take(10)
                ->get();
        }

        $formatted = $students->map(function (Student $student) {
            return [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->fullName,
                'registration_number' => $student->registration_number,
                'massar_code' => $student->massar_code,
                'gender' => $student->gender instanceof Gender ? $student->gender->value : (string) $student->gender,
                'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
                'photo' => $student->photo ? asset('storage/'.$student->photo) : null,
                'monthly_tuition_fee' => (float) $student->monthly_tuition_fee,
                'classroom' => $student->classroom ? [
                    'id' => $student->classroom->id,
                    'name' => $student->classroom->name,
                    'grade_level' => $student->classroom->gradeLevel?->name,
                ] : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'count' => $formatted->count(),
            'children' => $formatted,
            'data' => $formatted,
        ]);
    }

    /**
     * Return paginated session attendances for a child.
     */
    public function attendances(Request $request, int|string $student_id): JsonResponse
    {
        $user = $request->user();
        $student = Student::with(['guardian', 'classroom'])->findOrFail($student_id);

        // Security authorization check for guardian role
        if ($user && $user->role === 'guardian') {
            $guardian = $user->getGuardianRecord();
            if ($guardian && (int) $student->guardian_id !== (int) $guardian->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بالاطلاع على بيانات هذا التلميذ (Unauthorized access to student).',
                ], 403);
            }
        }

        $perPage = (int) $request->input('per_page', 15);

        $attendances = Attendance::where('student_id', $student->id)
            ->with(['timetable.subject', 'timetable.teacher', 'classroom'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $formatted = $attendances->getCollection()->map(function (Attendance $att) {
            $timetable = $att->timetable;

            $attachmentUrl = null;
            if ($att->guardian_justification_attachment) {
                $attachmentUrl = str_starts_with($att->guardian_justification_attachment, 'http')
                    ? $att->guardian_justification_attachment
                    : asset('storage/'.$att->guardian_justification_attachment);
            }

            return [
                'id' => $att->id,
                'date' => $att->date?->format('Y-m-d') ?? (string) $att->date,
                'status' => $att->status instanceof AttendanceStatus ? $att->status->value : (string) $att->status,
                'late_arrival_time' => $att->late_arrival_time,
                'justification_status' => $att->justification_status instanceof JustificationStatus ? $att->justification_status->value : (string) $att->justification_status,
                'guardian_justification_note' => $att->guardian_justification_note,
                'guardian_justification_attachment' => $attachmentUrl,
                'remarks' => $att->remarks,
                'timetable_id' => $att->timetable_id,
                'subject' => $timetable?->subject ? [
                    'id' => $timetable->subject->id,
                    'name' => $timetable->subject->name,
                ] : null,
                'teacher' => $timetable?->teacher ? [
                    'id' => $timetable->teacher->id,
                    'name' => $timetable->teacher->name,
                ] : null,
                'classroom' => $att->classroom?->name ?? $timetable?->classroom?->name,
                'session_time' => $timetable ? substr((string) $timetable->start_time, 0, 5).' - '.substr((string) $timetable->end_time, 0, 5) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'student' => [
                'id' => $student->id,
                'full_name' => $student->fullName,
                'classroom' => $student->classroom?->name,
            ],
            'data' => $formatted->values()->all(),
            'meta' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
            ],
        ]);
    }

    /**
     * Submit justification for an absence session.
     */
    public function justify(Request $request, int|string $attendance_id): JsonResponse
    {
        return app(GuardianAttendanceJustificationController::class)->submit($request);
    }
}
