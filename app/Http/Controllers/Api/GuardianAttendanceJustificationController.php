<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\SessionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuardianAttendanceJustificationController extends Controller
{
    public function __construct(
        protected SessionAttendanceService $attendanceService
    ) {}

    /**
     * Submit justification note and optional attachment for absent session.
     */
    public function submit(Request $request, int|string|Attendance|null $attendance = null): JsonResponse
    {
        $attendanceParam = $attendance
            ?? $request->route('attendance_id')
            ?? $request->route('attendance')
            ?? $request->input('attendance_id');

        if (! $attendanceParam) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance ID is required.',
            ], 422);
        }

        $targetAttendance = $attendanceParam instanceof Attendance
            ? $attendanceParam
            : Attendance::findOrFail($attendanceParam);

        $request->validate([
            'reason' => 'nullable|string',
            'note' => 'nullable|string',
            'guardian_justification_note' => 'nullable|string',
            'justification' => 'nullable|string',
            'attachment' => 'nullable',
            'guardian_justification_attachment' => 'nullable',
            'file' => 'nullable',
        ]);

        $note = $request->input('note')
            ?? $request->input('reason')
            ?? $request->input('guardian_justification_note')
            ?? $request->input('justification');

        if (blank($note)) {
            return response()->json([
                'success' => false,
                'message' => 'Justification reason/note is required.',
            ], 422);
        }

        $attachment = $request->file('attachment')
            ?? $request->file('file')
            ?? $request->input('attachment')
            ?? $request->input('guardian_justification_attachment');

        $updatedAttendance = $this->attendanceService->submitJustification(
            $targetAttendance,
            $note,
            $attachment
        );

        return response()->json([
            'success' => true,
            'message' => 'Guardian justification submitted successfully (تم إرسال تبرير الغياب بنجاح).',
            'data' => [
                'attendance_id' => $updatedAttendance->id,
                'student_id' => $updatedAttendance->student_id,
                'status' => $updatedAttendance->status?->value ?? (string) $updatedAttendance->status,
                'justification_status' => $updatedAttendance->justification_status?->value ?? (string) $updatedAttendance->justification_status,
                'guardian_justification_note' => $updatedAttendance->guardian_justification_note,
                'guardian_justification_attachment' => $updatedAttendance->guardian_justification_attachment,
            ],
        ], 200);
    }

    /**
     * Webhook endpoint for external Guardian responses.
     */
    public function webhook(Request $request): JsonResponse
    {
        return $this->submit($request);
    }
}
