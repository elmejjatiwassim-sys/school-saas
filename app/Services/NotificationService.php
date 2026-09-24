<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\School;
use App\Models\User;
use App\Notifications\AbsenceAlertNotification;
use App\Notifications\InvoiceReminderNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Arabic names for the 12 months.
     *
     * @var array<int, string>
     */
    public const ARABIC_MONTHS = [
        1 => 'يناير',
        2 => 'فبراير',
        3 => 'مارس',
        4 => 'أبريل',
        5 => 'ماي',
        6 => 'يونيو',
        7 => 'يوليوز',
        8 => 'غشت',
        9 => 'شتنبر',
        10 => 'أكتوبر',
        11 => 'نونبر',
        12 => 'دجنبر',
    ];

    /**
     * Dispatch in-app push/database notifications to guardians of absent students.
     *
     * @param  Collection<int, AttendanceRecord>|array<int, AttendanceRecord>  $absentRecords
     * @return array{sent_count: int, records_processed: int, notified_guardians: array<int, string>}
     */
    public function notifyGuardiansOfAbsence(Collection|array $absentRecords, School $school): array
    {
        $sentCount = 0;
        $notifiedGuardians = [];

        foreach ($absentRecords as $record) {
            $student = $record->student;
            if (! $student) {
                continue;
            }

            $guardian = $student->guardian;
            $notification = new AbsenceAlertNotification($student, $record);

            if ($guardian) {
                $guardian->notify($notification);
                $notifiedGuardians[] = "{$guardian->first_name} {$guardian->last_name} ({$guardian->phone}) - التلميذ: {$student->fullName}";

                // Also notify guardian User account if one exists
                if ($guardian->email) {
                    $guardianUser = User::where('school_id', $school->id)
                        ->where('email', $guardian->email)
                        ->first();
                    $guardianUser?->notify($notification);
                }
            }

            // Also notify student User account if active
            if ($student->user) {
                $student->user->notify($notification);
            }

            Log::info("Dispatched absence notification for student {$student->fullName} (School: {$school->name})");
            $sentCount++;
        }

        return [
            'sent_count' => $sentCount,
            'records_processed' => count($absentRecords),
            'notified_guardians' => $notifiedGuardians,
        ];
    }

    /**
     * Send monthly invoice reminder to guardian.
     */
    public function sendInvoiceReminder(Invoice $invoice, string $type = 'initial'): bool
    {
        $monthName = self::ARABIC_MONTHS[$invoice->billing_month] ?? (string) $invoice->billing_month;
        $studentName = $invoice->student ? "{$invoice->student->first_name} {$invoice->student->last_name}" : 'التلميذ';

        $message = match ($type) {
            'reminder' => "تذكير ودي: نرجو تسوية واجبات التمدرس لشهر {$monthName} الخاصة بالتلميذ {$studentName}.",
            default => "نحيطكم علماً بأن واجب التمدرس لشهر {$monthName} الخاص بالتلميذ {$studentName} متاح للأداء.",
        };

        $notification = new InvoiceReminderNotification($invoice, $message, $type);

        $guardian = $invoice->student?->guardian;
        if ($guardian) {
            $guardian->notify($notification);

            if ($guardian->email && $invoice->school_id) {
                $guardianUser = User::where('school_id', $invoice->school_id)
                    ->where('email', $guardian->email)
                    ->first();
                $guardianUser?->notify($notification);
            }
        }

        // Notify student if user account exists
        if ($invoice->student?->user) {
            $invoice->student->user->notify($notification);
        }

        Log::info("[Invoice Reminder Dispatched] Type: {$type}, Invoice: #{$invoice->invoice_number}, Student: {$studentName}, Message: {$message}");

        return true;
    }
}
