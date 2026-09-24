<?php

namespace Database\Seeders;

use App\Enums\AcademicMonth;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\AcademicYear;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InvoiceSeeder extends Seeder
{
    public function run(?School $school = null): void
    {
        $school ??= School::where('slug', 'al-amal')->first() ?? School::first();

        if (! $school) {
            return;
        }

        $academicYear = AcademicYear::where('school_id', $school->id)->where('is_current', true)->first()
            ?? AcademicYear::firstOrCreate(
                ['school_id' => $school->id, 'name' => '2026-2027'],
                ['start_date' => '2026-09-01', 'end_date' => '2027-06-30', 'is_current' => true]
            );

        // Seed Default Fee Types
        $tuitionFee = FeeType::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Monthly Tuition (واجب شهري)'],
            ['default_amount' => 1500.00, 'is_recurring_monthly' => true]
        );

        FeeType::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'Registration Fee (رسوم التسجيل)'],
            ['default_amount' => 1000.00, 'is_recurring_monthly' => false]
        );

        FeeType::firstOrCreate(
            ['school_id' => $school->id, 'name' => 'School Transport (نقل مدرسي)'],
            ['default_amount' => 500.00, 'is_recurring_monthly' => true]
        );

        $students = Student::where('school_id', $school->id)->take(5)->get();

        if ($students->isEmpty()) {
            return;
        }

        $monthsToSeed = [
            AcademicMonth::September,
            AcademicMonth::October,
        ];

        foreach ($students as $index => $student) {
            foreach ($monthsToSeed as $monthEnum) {
                $month = $monthEnum->value;
                $year = 2026;
                $monthPadded = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

                $invoice = Invoice::firstOrCreate(
                    [
                        'school_id' => $school->id,
                        'student_id' => $student->id,
                        'academic_year_id' => $academicYear->id,
                        'billing_month' => $month,
                        'billing_year' => $year,
                    ],
                    [
                        'invoice_number' => "INV-{$year}{$monthPadded}-{$student->id}-".strtoupper(Str::random(4)),
                        'title' => "واجب شهر {$monthEnum->getArabicName()} {$year}",
                        'total_amount' => 1500.00,
                        'paid_amount' => 0.00,
                        'due_date' => "{$year}-{$monthPadded}-05",
                        'status' => InvoiceStatus::Unpaid,
                    ]
                );

                // Add payments based on student index and month
                if ($month === 9) {
                    if ($index === 0 || $index === 1) {
                        // Fully paid (1500 MAD)
                        Payment::firstOrCreate(
                            ['invoice_id' => $invoice->id, 'receipt_number' => "REC-{$year}{$monthPadded}-00{$student->id}"],
                            [
                                'school_id' => $school->id,
                                'amount' => 1500.00,
                                'payment_date' => "{$year}-09-04",
                                'payment_method' => $index === 0 ? PaymentMethod::Cash : PaymentMethod::BankTransfer,
                                'notes' => 'Paiement complet en début de mois',
                            ]
                        );
                    } elseif ($index === 2) {
                        // Partially paid (800 MAD paid, 700 remaining)
                        Payment::firstOrCreate(
                            ['invoice_id' => $invoice->id, 'receipt_number' => "REC-{$year}{$monthPadded}-00{$student->id}"],
                            [
                                'school_id' => $school->id,
                                'amount' => 800.00,
                                'payment_date' => "{$year}-09-10",
                                'payment_method' => PaymentMethod::Cheque,
                                'notes' => 'Acompte versé par chèque',
                            ]
                        );
                    }
                    // index 3 & 4 remain unpaid
                } elseif ($month === 10) {
                    if ($index === 0) {
                        // Fully paid
                        Payment::firstOrCreate(
                            ['invoice_id' => $invoice->id, 'receipt_number' => "REC-{$year}{$monthPadded}-00{$student->id}"],
                            [
                                'school_id' => $school->id,
                                'amount' => 1500.00,
                                'payment_date' => "{$year}-10-02",
                                'payment_method' => PaymentMethod::Cash,
                                'notes' => 'Paiement comptant',
                            ]
                        );
                    } elseif ($index === 1) {
                        // Partial
                        Payment::firstOrCreate(
                            ['invoice_id' => $invoice->id, 'receipt_number' => "REC-{$year}{$monthPadded}-00{$student->id}"],
                            [
                                'school_id' => $school->id,
                                'amount' => 1000.00,
                                'payment_date' => "{$year}-10-05",
                                'payment_method' => PaymentMethod::BankTransfer,
                                'notes' => 'Virement bancaire partiel',
                            ]
                        );
                    }
                }

                $invoice->recalculatePaidAmountAndStatus();
            }
        }
    }
}
