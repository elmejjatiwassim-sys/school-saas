<?php

namespace App\Services;

use App\Enums\AiActionLogStatus;
use App\Enums\AttendanceSession;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Models\AcademicYear;
use App\Models\AiActionLog;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiCopilotService
{
    public function __construct(
        protected InvoiceGenerationService $invoiceService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Process prompt, call Gemini (or fallback), and return proposal or general chat.
     *
     * @return array{type: string, message: string, action_log: ?AiActionLog, proposal: ?array}
     */
    public function processPrompt(string $prompt, School $school, User $user): array
    {
        $parsed = $this->parsePromptWithGemini($prompt, $school);

        if (! empty($parsed['action_type']) && $parsed['action_type'] === 'notify_absent_students_guardians') {
            $parsed = $this->enrichNotifyAbsentPayload($parsed, $school);
        }

        if (! empty($parsed['action_type']) && ! empty($parsed['payload'])) {
            $log = AiActionLog::create([
                'school_id' => $school->id,
                'user_id' => $user->id,
                'action_type' => $parsed['action_type'],
                'payload' => $parsed['payload'],
                'status' => AiActionLogStatus::PendingConfirmation,
                'ip_address' => request()?->ip() ?? '127.0.0.1',
            ]);

            return [
                'type' => 'proposal',
                'message' => $parsed['summary'] ?? 'Action proposed. Please review the details below before confirmation.',
                'action_log' => $log,
                'proposal' => $parsed,
            ];
        }

        return [
            'type' => 'chat',
            'message' => $parsed['message'] ?? 'I have received your request. How else can I assist you with school administration?',
            'action_log' => null,
            'proposal' => null,
        ];
    }

    /**
     * Confirm and execute the proposed action.
     *
     * @return array{success: bool, message: string, log: AiActionLog}
     */
    public function executeAction(AiActionLog $log, User $user): array
    {
        abort_unless((int) $log->school_id === (int) $user->school_id, 403, 'Unauthorized school tenant.');

        if ($log->status !== AiActionLogStatus::PendingConfirmation) {
            throw new Exception("Action cannot be executed because its status is {$log->status->value}.");
        }

        $payload = $log->payload ?? [];
        $resultMessage = '';

        switch ($log->action_type) {
            case 'register_student':
                $resultMessage = $this->executeRegisterStudent($log->school_id, $payload);
                break;

            case 'quick_attendance':
                $resultMessage = $this->executeQuickAttendance($log->school_id, $payload);
                break;

            case 'generate_invoices':
                $resultMessage = $this->executeGenerateInvoices($log->school_id, $payload);
                break;

            case 'notify_absent_students_guardians':
                $resultMessage = $this->executeNotifyAbsentGuardians($log->school_id, $payload);
                break;

            default:
                throw new Exception("Unknown action type: {$log->action_type}");
        }

        $log->update([
            'status' => AiActionLogStatus::Executed,
            'confirmed_at' => now(),
            'user_id' => $user->id,
        ]);

        return [
            'success' => true,
            'message' => $resultMessage,
            'log' => $log->fresh(),
        ];
    }

    /**
     * Cancel the proposed action.
     */
    public function cancelAction(AiActionLog $log, User $user): AiActionLog
    {
        abort_unless((int) $log->school_id === (int) $user->school_id, 403, 'Unauthorized school tenant.');

        $log->update([
            'status' => AiActionLogStatus::Cancelled,
            'confirmed_at' => null,
        ]);

        return $log->fresh();
    }

    protected function executeRegisterStudent(int $schoolId, array $payload): string
    {
        $classroomId = $payload['classroom_id'] ?? null;
        if (! $classroomId && ! empty($payload['classroom_name'])) {
            $classroomId = Classroom::where('school_id', $schoolId)
                ->where('name', 'like', "%{$payload['classroom_name']}%")
                ->value('id');
        }

        if (! $classroomId) {
            $classroomId = Classroom::where('school_id', $schoolId)->value('id');
        }

        if (! $classroomId) {
            $academicYear = AcademicYear::where('school_id', $schoolId)->first()
                ?? AcademicYear::create([
                    'school_id' => $schoolId,
                    'name' => '2026-2027',
                    'is_current' => true,
                ]);

            $classroom = Classroom::create([
                'school_id' => $schoolId,
                'academic_year_id' => $academicYear->id,
                'name' => $payload['classroom_name'] ?? 'Class A',
            ]);
            $classroomId = $classroom->id;
        }

        $guardianId = null;
        if (! empty($payload['guardian_phone']) || ! empty($payload['guardian_name'])) {
            $phone = $payload['guardian_phone'] ?? '0600000000';
            $guardianName = $payload['guardian_name'] ?? 'Guardian';
            $nameParts = explode(' ', $guardianName, 2);

            $guardian = Guardian::firstOrCreate(
                ['school_id' => $schoolId, 'phone' => $phone],
                [
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? 'Parent',
                    'relationship_type' => 'father',
                ]
            );
            $guardianId = $guardian->id;
        }

        $gender = match (strtolower((string) ($payload['gender'] ?? 'male'))) {
            'female', 'f', 'أنثى', 'fille' => Gender::Female,
            default => Gender::Male,
        };

        $student = Student::create([
            'school_id' => $schoolId,
            'classroom_id' => $classroomId,
            'guardian_id' => $guardianId,
            'first_name' => $payload['first_name'] ?? 'Student',
            'last_name' => $payload['last_name'] ?? 'Name',
            'gender' => $gender,
            'date_of_birth' => ! empty($payload['date_of_birth']) ? $payload['date_of_birth'] : null,
            'status' => 'active',
        ]);

        $classroomName = Classroom::find($classroomId)?->name ?? 'Classroom';

        return "Successfully registered student {$student->first_name} {$student->last_name} (Registration No: {$student->registration_number}) in {$classroomName}.";
    }

    protected function executeQuickAttendance(int $schoolId, array $payload): string
    {
        $date = $payload['date'] ?? now()->toDateString();
        $session = match (strtolower((string) ($payload['session'] ?? 'morning'))) {
            'afternoon', 'مساء' => AttendanceSession::Afternoon->value,
            'full_day', 'يوم كامل' => AttendanceSession::FullDay->value,
            default => AttendanceSession::Morning->value,
        };

        $status = match (strtolower((string) ($payload['status'] ?? 'present'))) {
            'absent', 'غائب' => AttendanceStatus::Absent->value,
            'late', 'متأخر' => AttendanceStatus::Late->value,
            'excused', 'مبرر' => AttendanceStatus::Excused->value,
            default => AttendanceStatus::Present->value,
        };

        $academicYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first()
            ?? AcademicYear::where('school_id', $schoolId)->first();

        if (! $academicYear) {
            throw new Exception('No academic year configured for this school.');
        }

        // Target student
        $studentQuery = Student::where('school_id', $schoolId);
        if (! empty($payload['student_id'])) {
            $studentQuery->where('id', $payload['student_id']);
        } elseif (! empty($payload['student_name'])) {
            $search = trim($payload['student_name']);
            $parts = array_filter(explode(' ', $search));
            $studentQuery->where(function ($q) use ($parts) {
                foreach ($parts as $part) {
                    $q->where(function ($sub) use ($part) {
                        $sub->where('first_name', 'like', "%{$part}%")
                            ->orWhere('last_name', 'like', "%{$part}%");
                    });
                }
            });
        }

        $student = $studentQuery->first();

        if (! $student) {
            throw new Exception("Student not found for attendance marking: {$payload['student_name']}");
        }

        AttendanceRecord::updateOrCreate(
            [
                'school_id' => $schoolId,
                'student_id' => $student->id,
                'date' => $date,
                'session' => $session,
            ],
            [
                'classroom_id' => $student->classroom_id,
                'academic_year_id' => $academicYear->id,
                'status' => $status,
                'remarks' => $payload['remarks'] ?? 'Marked via Admin AI Copilot',
            ]
        );

        return "Attendance recorded for {$student->first_name} {$student->last_name}: marked as {$status} on {$date} ({$session}).";
    }

    protected function executeGenerateInvoices(int $schoolId, array $payload): string
    {
        $academicYear = AcademicYear::where('school_id', $schoolId)->where('is_current', true)->first()
            ?? AcademicYear::where('school_id', $schoolId)->first();

        if (! $academicYear) {
            throw new Exception('No academic year configured for this school.');
        }

        $monthlyAmount = (float) ($payload['monthly_amount'] ?? 1500.00);

        if (! empty($payload['classroom_name']) || ! empty($payload['classroom_id'])) {
            $classroomQuery = Classroom::where('school_id', $schoolId);
            if (! empty($payload['classroom_id'])) {
                $classroomQuery->where('id', $payload['classroom_id']);
            } else {
                $classroomQuery->where('name', 'like', "%{$payload['classroom_name']}%");
            }
            $classroom = $classroomQuery->firstOrFail();

            $count = $this->invoiceService->generateForClassroom($classroom, $academicYear, $monthlyAmount);

            return "Generated {$count} invoices for classroom {$classroom->name} (September to June).";
        }

        if (! empty($payload['student_name']) || ! empty($payload['student_id'])) {
            $studentQuery = Student::where('school_id', $schoolId);
            if (! empty($payload['student_id'])) {
                $studentQuery->where('id', $payload['student_id']);
            } else {
                $search = trim($payload['student_name']);
                $parts = array_filter(explode(' ', $search));
                $studentQuery->where(function ($q) use ($parts) {
                    foreach ($parts as $part) {
                        $q->where(function ($sub) use ($part) {
                            $sub->where('first_name', 'like', "%{$part}%")
                                ->orWhere('last_name', 'like', "%{$part}%");
                        });
                    }
                });
            }
            $student = $studentQuery->firstOrFail();

            $count = $this->invoiceService->generateForStudent($student, $academicYear, $monthlyAmount);

            return "Generated {$count} invoices for {$student->first_name} {$student->last_name} (September to June).";
        }

        throw new Exception('Please specify a student or classroom for invoice generation.');
    }

    /**
     * Enrich the notify_absent_students_guardians proposal with actual database records and guardian details.
     */
    protected function enrichNotifyAbsentPayload(array $parsed, School $school): array
    {
        $payload = $parsed['payload'] ?? [];
        $date = $payload['date'] ?? now()->toDateString();
        $classroomIdOrName = $payload['classroom_id_or_name'] ?? $payload['classroom_name'] ?? null;

        $query = AttendanceRecord::where('school_id', $school->id)
            ->whereDate('date', $date)
            ->where('status', AttendanceStatus::Absent)
            ->with(['student.guardian', 'student.user', 'classroom']);

        $resolvedClassroom = null;
        if (! empty($classroomIdOrName) && $classroomIdOrName !== 'All Classrooms') {
            if (is_numeric($classroomIdOrName)) {
                $query->where('classroom_id', (int) $classroomIdOrName);
                $resolvedClassroom = Classroom::where('school_id', $school->id)->find($classroomIdOrName);
            } else {
                $query->whereHas('classroom', fn ($q) => $q->where('name', 'like', "%{$classroomIdOrName}%"));
                $resolvedClassroom = Classroom::where('school_id', $school->id)->where('name', 'like', "%{$classroomIdOrName}%")->first();
            }
        }

        $records = $query->get();

        $absentDetails = $records->map(function ($r) use ($school) {
            $guardian = $r->student?->guardian;
            $guardianUser = $guardian && $guardian->email
                ? User::where('school_id', $school->id)->where('email', $guardian->email)->first()
                : null;

            return [
                'student_id' => $r->student_id,
                'student_name' => $r->student?->fullName ?? 'Unknown',
                'classroom' => $r->classroom?->name ?? 'N/A',
                'guardian_name' => $guardian ? $guardian->fullName : 'No Guardian',
                'guardian_phone' => $guardian?->phone ?? 'N/A',
                'guardian_account' => $guardianUser ? 'Active User (حساب مفعل)' : 'SMS/Phone Only',
            ];
        })->values()->all();

        $studentNamesSummary = $records->map(fn ($r) => $r->student?->fullName)->filter()->implode(', ');
        $classroomLabel = $resolvedClassroom?->name ?? 'All Classrooms (كل الفصول)';

        $parsed['payload'] = [
            'date' => $date,
            'classroom' => $classroomLabel,
            'classroom_id' => $resolvedClassroom?->id,
            'absent_students_count' => $records->count(),
            'absent_students' => $studentNamesSummary ?: 'None (لا يوجد تلاميذ غائبين)',
            'details' => $absentDetails,
        ];

        $parsed['summary'] = "إرسال إشعارات غياب إلى أولياء أمور {$records->count()} تلميذ غائب بتاريخ {$date} ({$classroomLabel}).";

        return $parsed;
    }

    /**
     * Execute absence notifications dispatch to guardians.
     */
    protected function executeNotifyAbsentGuardians(int $schoolId, array $payload): string
    {
        $date = $payload['date'] ?? now()->toDateString();
        $school = School::findOrFail($schoolId);

        $query = AttendanceRecord::where('school_id', $schoolId)
            ->whereDate('date', $date)
            ->where('status', AttendanceStatus::Absent)
            ->with(['student.guardian', 'student.user']);

        if (! empty($payload['classroom_id'])) {
            $query->where('classroom_id', $payload['classroom_id']);
        } elseif (! empty($payload['classroom']) && $payload['classroom'] !== 'All Classrooms' && ! str_contains($payload['classroom'], 'كل الفصول')) {
            $query->whereHas('classroom', fn ($q) => $q->where('name', 'like', "%{$payload['classroom']}%"));
        }

        $records = $query->get();

        $result = $this->notificationService->notifyGuardiansOfAbsence($records, $school);

        return "Successfully dispatched absence notifications to {$result['sent_count']} guardians for date {$date}.";
    }

    /**
     * Parse prompt with Gemini API or rule-based fallback.
     */
    protected function parsePromptWithGemini(string $prompt, School $school): array
    {
        $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
        $model = config('services.gemini.model', 'gemini-1.5-flash');

        if (blank($apiKey)) {
            return $this->parsePromptFallback($prompt);
        }

        $systemPrompt = <<<'SYS'
You are an AI Copilot for school administrators. You analyze commands and determine if the user wants to execute an action.
Possible actions:
1. 'register_student':
   Required fields: first_name, last_name, classroom_name
   Optional fields: guardian_name, guardian_phone, gender (male/female), date_of_birth (YYYY-MM-DD)
2. 'quick_attendance':
   Required fields: student_name, status (present/absent/late/excused)
   Optional fields: date (YYYY-MM-DD, default today), session (morning/afternoon/full_day), remarks
3. 'generate_invoices':
   Required fields: classroom_name OR student_name
   Optional fields: monthly_amount (numeric default 1500)
4. 'notify_absent_students_guardians':
   Optional fields: classroom_id_or_name (classroom name or ID), date (YYYY-MM-DD, default today)
   Use when the administrator wants to notify parents/guardians of absent students or send absence alerts.

If the prompt matches one of these actions, return a JSON object with:
{
  "action_type": "register_student" | "quick_attendance" | "generate_invoices" | "notify_absent_students_guardians",
  "summary": "Short 1-sentence summary of the action to be confirmed",
  "payload": { ...extracted parameters... }
}

If the prompt is just conversational, informational, or not one of these actions, return:
{
  "action_type": null,
  "message": "Friendly response answering the user or asking for needed details"
}
Return ONLY pure JSON.
SYS;

        try {
            $response = Http::timeout(10)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => "{$systemPrompt}\n\nUser Prompt: {$prompt}"],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                ],
            ]);

            if ($response->successful()) {
                $candidates = $response->json('candidates');
                $text = $candidates[0]['content']['parts'][0]['text'] ?? '';
                $decoded = json_decode($text, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        } catch (Exception $e) {
            Log::warning('Gemini API call failed, falling back to rule-based parser: '.$e->getMessage());
        }

        return $this->parsePromptFallback($prompt);
    }

    /**
     * Rule-based fallback parser for offline/test environments.
     */
    protected function parsePromptFallback(string $prompt): array
    {
        $lower = mb_strtolower($prompt);

        // 1. Student Registration
        if (str_contains($lower, 'register') || str_contains($lower, 'تسجيل تلميذ') || str_contains($lower, 'inscrire')) {
            $firstName = 'Karim';
            $lastName = 'Bennani';
            $classroom = 'CP-A';
            $phone = null;

            // Pattern: Register student [First] [Last] in [Classroom]
            if (preg_match('/(?:register student|inscrire l\'élève|تسجيل تلميذ)\s+([A-Za-z\p{Arabic}]+)\s+([A-Za-z\p{Arabic}]+)(?:\s+(?:in|dans|في قسم|في)\s+([A-Za-z0-9\-\p{Arabic}]+))?/iu', $prompt, $matches)) {
                $firstName = $matches[1];
                $lastName = $matches[2];
                if (! empty($matches[3])) {
                    $classroom = $matches[3];
                }
            }

            if (preg_match('/(?:phone|tel|téléphone|هاتف)\s*[:=]?\s*([0-9+\s\-]{8,15})/i', $prompt, $phoneMatch)) {
                $phone = trim($phoneMatch[1]);
            }

            return [
                'action_type' => 'register_student',
                'summary' => "Register new student {$firstName} {$lastName} in classroom {$classroom}",
                'payload' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'classroom_name' => $classroom,
                    'guardian_phone' => $phone ?? '0612345678',
                    'guardian_name' => "Parent of {$firstName}",
                    'gender' => 'male',
                ],
            ];
        }

        // 2. Notify Absent Students' Guardians
        if (
            str_contains($lower, 'notify absent') ||
            str_contains($lower, 'absence notification') ||
            str_contains($lower, 'notify guardian') ||
            str_contains($lower, 'notify parent') ||
            str_contains($lower, 'إشعار غياب') ||
            str_contains($lower, 'إشعار الغياب') ||
            str_contains($lower, 'تنبيه غياب') ||
            str_contains($lower, 'إعلام أولياء') ||
            str_contains($lower, 'بلغ أولياء') ||
            str_contains($lower, 'تلاميذ غائبين') ||
            str_contains($lower, 'تنبيه أولياء')
        ) {
            $date = now()->toDateString();
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $prompt, $dateMatch)) {
                $date = $dateMatch[1];
            }

            $classroom = null;
            if (preg_match('/(?:classroom|class|classe|قسم|فصل)\s+([A-Za-z0-9\-\p{Arabic}]+)/iu', $prompt, $cMatch)) {
                $classroom = trim($cMatch[1]);
            }

            return [
                'action_type' => 'notify_absent_students_guardians',
                'summary' => "Notify guardians of absent students for date {$date}",
                'payload' => [
                    'date' => $date,
                    'classroom_id_or_name' => $classroom,
                ],
            ];
        }

        // 3. Quick Attendance
        if (
            ! str_contains($lower, 'notify') &&
            ! str_contains($lower, 'إشعار') &&
            ! str_contains($lower, 'تنبيه') &&
            ! str_contains($lower, 'بلغ') &&
            (str_contains($lower, 'attendance') || str_contains($lower, 'absent') || str_contains($lower, 'غياب') || str_contains($lower, 'حضور'))
        ) {
            $status = 'present';
            if (str_contains($lower, 'absent') || str_contains($lower, 'غائب')) {
                $status = 'absent';
            } elseif (str_contains($lower, 'late') || str_contains($lower, 'متأخر')) {
                $status = 'late';
            } elseif (str_contains($lower, 'excused') || str_contains($lower, 'مبرر')) {
                $status = 'excused';
            }

            $studentName = 'Omar';
            if (preg_match('/(?:mark|تسجيل غياب|تسجيل حضور)\s+([A-Za-z\p{Arabic}]+(?:\s+[A-Za-z\p{Arabic}]+)?)/iu', $prompt, $sMatches)) {
                $studentName = trim($sMatches[1]);
                $studentName = preg_replace('/(as|absent|present|late|excused|اليوم|غائب|حاضر)/i', '', $studentName);
                $studentName = trim($studentName) ?: 'Omar';
            }

            return [
                'action_type' => 'quick_attendance',
                'summary' => "Mark attendance as '{$status}' for student {$studentName}",
                'payload' => [
                    'student_name' => $studentName,
                    'status' => $status,
                    'date' => now()->toDateString(),
                    'session' => str_contains($lower, 'afternoon') ? 'afternoon' : 'morning',
                    'remarks' => 'Marked via Admin AI Copilot',
                ],
            ];
        }

        // 4. Generate Invoices
        if (str_contains($lower, 'invoice') || str_contains($lower, 'فاتورة') || str_contains($lower, 'فواتير') || str_contains($lower, 'facture')) {
            $classroom = 'CP-A';
            if (preg_match('/(?:for classroom|pour la classe|لقسم|لفصل)\s+([A-Za-z0-9\-\p{Arabic}]+)/iu', $prompt, $cMatches)) {
                $classroom = trim($cMatches[1]);
            }

            return [
                'action_type' => 'generate_invoices',
                'summary' => "Generate 10 months tuition invoices for classroom {$classroom}",
                'payload' => [
                    'classroom_name' => $classroom,
                    'monthly_amount' => 1500.00,
                ],
            ];
        }

        // Default: Chat response
        return [
            'action_type' => null,
            'message' => 'Hello! I am your Admin AI Copilot. You can ask me to register students, record attendance, generate invoices, or notify guardians of absent students with confirmation.',
        ];
    }
}
