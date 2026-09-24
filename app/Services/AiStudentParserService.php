<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\GuardianRelationshipType;
use App\Enums\StudentStatus;
use App\Models\Guardian;
use App\Models\Student;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiStudentParserService
{
    /**
     * Parse student list from a file (PDF, image, CSV, spreadsheet) using Gemini API.
     *
     * @return array<int, array{first_name: string, last_name: string, massar_code: ?string, gender: string, date_of_birth: ?string, guardian_name: ?string, guardian_phone: ?string}>
     */
    public function parseFile(string $filePath, ?string $mimeType = null): array
    {
        if (! file_exists($filePath)) {
            throw new Exception("File not found at: {$filePath}");
        }

        $mimeType ??= $this->detectMimeType($filePath);
        $fileContents = file_get_contents($filePath);

        $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
        $model = config('services.gemini.model', 'gemini-1.5-flash');

        // Fallback for CSV if API key is not configured
        if (blank($apiKey) && in_array($mimeType, ['text/csv', 'text/plain'], true)) {
            return $this->parseCsvFallback($fileContents);
        }

        if (blank($apiKey)) {
            throw new Exception('GEMINI_API_KEY is not configured in .env or config/services.php.');
        }

        $prompt = <<<'PROMPT'
You are an expert document parser for school management systems in Morocco and internationally.
Extract all students listed in the provided document (which may be in Arabic, French, or English, from a PDF, table, image, attendance sheet, or spreadsheet).

Focus STRICTLY on the primary student data:
- first_name: Student first name (الاسم الشخصي / Prénom) (REQUIRED)
- last_name: Student family name (الاسم العائلي / Nom) (REQUIRED)
- massar_code: Massar code / Code Massar if available (typically 1 letter followed by 9 digits, e.g. G123456789), or null if not found.
- gender: normalize strictly to 'male' or 'female' (e.g. from M/F, ذكر/أنثى, Garçon/Fille, G/F, H/F). If not mentioned, infer from first name or default to 'male'.
- date_of_birth: YYYY-MM-DD format (تاريخ الازدياد / Date de naissance) or null if unreadable.
- guardian_name: optional / nullable.
- guardian_phone: optional / nullable.
- opening_balance: numeric decimal amount if a column for Solde, متأخرات, Solde restant, Reste, Balance, متأخرات سابقة is present, otherwise 0.00.

Return ONLY a valid JSON array of objects with keys:
"first_name", "last_name", "massar_code", "gender", "date_of_birth", "guardian_name", "guardian_phone", "opening_balance".
Do not wrap in backticks or markdown, return only the raw JSON array.
PROMPT;

        $parts = [
            ['text' => $prompt],
        ];

        if (in_array($mimeType, ['text/csv', 'text/plain'], true)) {
            $parts[] = ['text' => "Document Content:\n".$fileContents];
        } else {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mimeType,
                    'data' => base64_encode($fileContents),
                ],
            ];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(60)->post($url, [
            'contents' => [
                ['parts' => $parts],
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.1,
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Gemini API Error: '.$response->body());
            throw new Exception('Gemini API request failed: '.$response->status().' - '.$response->json('error.message', $response->body()));
        }

        $rawText = $response->json('candidates.0.content.parts.0.text', '');
        $rawText = trim($rawText);
        $rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
        $rawText = preg_replace('/\s*```$/', '', $rawText);

        $parsed = json_decode($rawText, true);

        if (! is_array($parsed)) {
            throw new Exception('Unable to parse JSON response from Gemini: '.$rawText);
        }

        return $this->normalizeStudentsData($parsed);
    }

    /**
     * Import parsed student data into the database for a specific school and classroom.
     *
     * @param  array<int, array<string, mixed>>  $studentsData
     * @return int Number of students imported
     */
    public function importStudents(array $studentsData, int $schoolId, int $classroomId): int
    {
        $importedCount = 0;

        foreach ($studentsData as $item) {
            $firstName = trim((string) ($item['first_name'] ?? ''));
            $lastName = trim((string) ($item['last_name'] ?? ''));

            if (empty($firstName) || empty($lastName)) {
                continue;
            }

            // Normalize gender
            $genderStr = strtolower(trim((string) ($item['gender'] ?? 'male')));
            $gender = in_array($genderStr, ['female', 'f', 'أنثى', 'fille'], true) ? Gender::Female : Gender::Male;

            // Normalize date of birth
            $dob = null;
            if (! empty($item['date_of_birth'])) {
                try {
                    $dob = Carbon::parse($item['date_of_birth'])->format('Y-m-d');
                } catch (\Throwable) {
                    $dob = null;
                }
            }

            // Handle optional guardian
            $guardianId = null;
            $guardianPhone = ! empty($item['guardian_phone']) ? trim((string) $item['guardian_phone']) : null;
            $guardianName = ! empty($item['guardian_name']) ? trim((string) $item['guardian_name']) : null;

            if ($guardianPhone || $guardianName) {
                $gFirst = $guardianName ? explode(' ', $guardianName)[0] : 'Guardian';
                $gLast = $guardianName && str_contains($guardianName, ' ')
                    ? substr($guardianName, strpos($guardianName, ' ') + 1)
                    : $lastName;
                $phone = $guardianPhone ?? '+2126'.fake()->numerify('########');

                $guardian = Guardian::firstOrCreate(
                    [
                        'school_id' => $schoolId,
                        'phone' => $phone,
                    ],
                    [
                        'first_name' => $gFirst,
                        'last_name' => $gLast,
                        'relationship_type' => GuardianRelationshipType::Father,
                    ]
                );

                $guardianId = $guardian->id;
            }

            // Create student (registration_number is auto-generated by Student creating event)
            Student::create([
                'school_id' => $schoolId,
                'classroom_id' => $classroomId,
                'guardian_id' => $guardianId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'massar_code' => ! empty($item['massar_code']) ? trim((string) $item['massar_code']) : null,
                'date_of_birth' => $dob,
                'gender' => $gender,
                'opening_balance' => (float) ($item['opening_balance'] ?? 0.00),
                'status' => StudentStatus::Active,
            ]);

            $importedCount++;
        }

        return $importedCount;
    }

    /**
     * Normalize parsed student array items.
     *
     * @param  array<mixed>  $items
     * @return array<int, array{first_name: string, last_name: string, massar_code: ?string, gender: string, date_of_birth: ?string, guardian_name: ?string, guardian_phone: ?string, opening_balance: float}>
     */
    protected function normalizeStudentsData(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $firstName = trim((string) ($item['first_name'] ?? $item['prenom'] ?? $item['الاسم_الشخصي'] ?? ''));
            $lastName = trim((string) ($item['last_name'] ?? $item['nom'] ?? $item['الاسم_العائلي'] ?? ''));

            if (empty($firstName) || empty($lastName)) {
                continue;
            }

            $genderStr = strtolower(trim((string) ($item['gender'] ?? $item['sexe'] ?? $item['الجنس'] ?? 'male')));
            $gender = in_array($genderStr, ['female', 'f', 'أنثى', 'fille'], true) ? 'female' : 'male';

            $dob = null;
            $rawDob = $item['date_of_birth'] ?? $item['date_naissance'] ?? $item['تاريخ_الازدياد'] ?? null;
            if (! empty($rawDob)) {
                try {
                    $dob = Carbon::parse($rawDob)->format('Y-m-d');
                } catch (\Throwable) {
                    $dob = null;
                }
            }

            $rawBalance = $item['opening_balance'] ?? $item['solde'] ?? $item['متأخرات'] ?? $item['متأخرات_سابقة'] ?? $item['reste'] ?? $item['balance'] ?? 0.00;
            $openingBalance = 0.00;
            if (is_numeric($rawBalance)) {
                $openingBalance = max(0.00, round((float) $rawBalance, 2));
            } elseif (is_string($rawBalance) && preg_match('/[\d.,]+/', $rawBalance, $matches)) {
                $sanitized = str_replace(',', '.', $matches[0]);
                $openingBalance = max(0.00, round((float) $sanitized, 2));
            }

            $normalized[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'massar_code' => ! empty($item['massar_code']) ? (string) $item['massar_code'] : null,
                'gender' => $gender,
                'date_of_birth' => $dob,
                'guardian_name' => ! empty($item['guardian_name']) ? (string) $item['guardian_name'] : null,
                'guardian_phone' => ! empty($item['guardian_phone']) ? (string) $item['guardian_phone'] : null,
                'opening_balance' => $openingBalance,
            ];
        }

        return $normalized;
    }

    /**
     * Fallback parser for CSV files when GEMINI_API_KEY is not set.
     *
     * @return array<int, array{first_name: string, last_name: string, massar_code: ?string, gender: string, date_of_birth: ?string, guardian_name: ?string, guardian_phone: ?string, opening_balance: float}>
     */
    protected function parseCsvFallback(string $content): array
    {
        $lines = explode("\n", trim($content));
        if (count($lines) < 2) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => strtolower(trim($h)), $header);

        $students = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $row = str_getcsv($line);
            if (count($row) < 2) {
                continue;
            }

            $data = array_combine(array_slice($header, 0, count($row)), $row);

            $students[] = [
                'first_name' => $data['first_name'] ?? $data['prenom'] ?? $row[0] ?? '',
                'last_name' => $data['last_name'] ?? $data['nom'] ?? $row[1] ?? '',
                'massar_code' => $data['massar_code'] ?? $data['code_massar'] ?? null,
                'gender' => $data['gender'] ?? $data['sexe'] ?? 'male',
                'date_of_birth' => $data['date_of_birth'] ?? $data['dob'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? null,
                'opening_balance' => $data['opening_balance'] ?? $data['solde'] ?? $data['متأخرات'] ?? $data['reste'] ?? $data['balance'] ?? 0.00,
            ];
        }

        return $this->normalizeStudentsData($students);
    }

    /**
     * Detect file mime type from extension or file info.
     */
    protected function detectMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };
    }
}
