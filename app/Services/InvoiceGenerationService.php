<?php

namespace App\Services;

use App\Enums\AcademicMonth;
use App\Enums\FeeInvoiceType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Str;

class InvoiceGenerationService
{
    /**
     * Generate 10-month tuition invoices (September to June) for a given student.
     *
     * @return int Number of invoices created
     */
    public function generateForStudent(
        Student $student,
        AcademicYear $academicYear,
        float $monthlyAmount,
        int $dueDay = 5
    ): int {
        $startYear = $academicYear->start_date ? Carbon::parse($academicYear->start_date)->year : (int) explode('-', $academicYear->name)[0];
        $endYear = $academicYear->end_date ? Carbon::parse($academicYear->end_date)->year : ($startYear + 1);

        $school = $student->school ?? School::find($student->school_id);
        $billingStartMonth = $school?->billing_start_date ? Carbon::parse($school->billing_start_date)->format('Y-m') : null;

        $createdCount = 0;

        foreach (AcademicMonth::academicCycleMonths() as $monthEnum) {
            $month = $monthEnum->value;
            $year = in_array($month, [9, 10, 11, 12], true) ? $startYear : $endYear;

            // Do not generate monthly tuition invoices for months prior to the school's billing_start_date
            if ($billingStartMonth !== null) {
                $cycleMonth = sprintf('%04d-%02d', $year, $month);
                if ($cycleMonth < $billingStartMonth) {
                    continue;
                }
            }

            $exists = Invoice::where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->where('billing_month', $month)
                ->where('billing_year', $year)
                ->exists();

            if ($exists) {
                continue;
            }

            $monthPadded = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $dayPadded = str_pad((string) min(28, max(1, $dueDay)), 2, '0', STR_PAD_LEFT);
            $invoiceNumber = 'INV-'.$year.$monthPadded.'-'.$student->id.'-'.strtoupper(Str::random(4));

            Invoice::create([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'invoice_number' => $invoiceNumber,
                'type' => InvoiceType::Monthly,
                'invoice_type' => FeeInvoiceType::MonthlyFee,
                'billing_month' => $month,
                'billing_year' => $year,
                'title' => 'واجب شهر '.$monthEnum->getArabicName().' '.$year,
                'total_amount' => $monthlyAmount,
                'paid_amount' => 0.00,
                'due_date' => "{$year}-{$monthPadded}-{$dayPadded}",
                'status' => InvoiceStatus::Unpaid,
            ]);

            $createdCount++;
        }

        return $createdCount;
    }

    /**
     * Generate 10-month tuition invoices for all students in a classroom.
     *
     * @return int Total number of invoices created
     */
    public function generateForClassroom(
        Classroom $classroom,
        AcademicYear $academicYear,
        float $monthlyAmount,
        int $dueDay = 5
    ): int {
        $totalCreated = 0;
        $students = $classroom->students()->where('status', 'active')->get();

        foreach ($students as $student) {
            $totalCreated += $this->generateForStudent($student, $academicYear, $monthlyAmount, $dueDay);
        }

        return $totalCreated;
    }

    /**
     * Generate an Annual Package Lump-Sum Invoice (10 Months Tuition + Registration + Insurance)
     */
    public function generateAnnualPackage(
        Student $student,
        AcademicYear $academicYear,
        float $monthlyTuition,
        float $registrationFee,
        float $insuranceFee,
        ?string $dueDate = null
    ): Invoice {
        $totalTuition = round($monthlyTuition * 10, 2);
        $totalAmount = round($totalTuition + $registrationFee + $insuranceFee, 2);
        $startYear = $academicYear->start_date ? Carbon::parse($academicYear->start_date)->year : (int) explode('-', $academicYear->name)[0];
        $dueDate ??= "{$startYear}-09-05";

        $invoiceNumber = 'INV-ANN-'.$startYear.'-'.$student->id.'-'.strtoupper(Str::random(4));

        $invoice = Invoice::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'invoice_number' => $invoiceNumber,
            'type' => InvoiceType::AnnualPackage,
            'billing_month' => null,
            'billing_year' => $startYear,
            'title' => "عرض سنوي شامل {$academicYear->name} - (واجب 10 أشهر + تسجيل + تأمين)",
            'total_amount' => $totalAmount,
            'paid_amount' => 0.00,
            'due_date' => $dueDate,
            'status' => InvoiceStatus::Unpaid,
        ]);

        // Line Item 1: 10 Months Tuition
        $invoice->items()->create([
            'description' => 'Monthly Tuition / واجب دراسي (10 Months / أشهر: شتنبر - يونيو)',
            'amount' => $monthlyTuition,
            'quantity' => 10,
        ]);

        // Line Item 2: Registration Fee
        if ($registrationFee > 0) {
            $invoice->items()->create([
                'description' => 'Registration Fee / واجب التسجيل السنوي',
                'amount' => $registrationFee,
                'quantity' => 1,
            ]);
        }

        // Line Item 3: Insurance Fee
        if ($insuranceFee > 0) {
            $invoice->items()->create([
                'description' => 'Insurance Fee / واجب التأمين المدرسي',
                'amount' => $insuranceFee,
                'quantity' => 1,
            ]);
        }

        return $invoice;
    }

    /**
     * Generate an Opening Balance Invoice when a student is created with opening_balance > 0.
     */
    public function createOpeningBalanceInvoice(Student $student): ?Invoice
    {
        if ((float) $student->opening_balance <= 0) {
            return null;
        }

        $existing = Invoice::where('student_id', $student->id)
            ->where('invoice_type', FeeInvoiceType::OpeningBalance->value)
            ->first();

        if ($existing) {
            return $existing;
        }

        $academicYearId = $student->classroom?->academic_year_id
            ?? AcademicYear::where('school_id', $student->school_id)->where('is_current', true)->value('id')
            ?? AcademicYear::where('school_id', $student->school_id)->latest('id')->value('id')
            ?? AcademicYear::where('is_current', true)->value('id')
            ?? AcademicYear::latest('id')->value('id');

        if (! $academicYearId && $student->school_id) {
            $academicYear = AcademicYear::firstOrCreate(
                ['school_id' => $student->school_id, 'name' => date('Y').'-'.(date('Y') + 1)],
                ['start_date' => date('Y').'-09-01', 'end_date' => (date('Y') + 1).'-06-30', 'is_current' => true]
            );
            $academicYearId = $academicYear->id;
        }

        if (! $academicYearId) {
            return null;
        }

        $year = (int) date('Y');
        $invoiceNumber = 'INV-OPB-'.$year.'-'.$student->id.'-'.strtoupper(Str::random(4));

        $invoice = Invoice::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYearId,
            'invoice_number' => $invoiceNumber,
            'type' => InvoiceType::Custom,
            'invoice_type' => FeeInvoiceType::OpeningBalance,
            'billing_month' => null,
            'billing_year' => $year,
            'title' => 'رصيد افتتاحي / متأخرات سابقة - Opening Balance',
            'total_amount' => $student->opening_balance,
            'paid_amount' => 0.00,
            'due_date' => now()->toDateString(),
            'status' => InvoiceStatus::Unpaid,
        ]);

        $invoice->items()->create([
            'description' => 'Opening Balance / متأخرات سابقة ورصيد افتتاحي',
            'amount' => $student->opening_balance,
            'quantity' => 1,
        ]);

        return $invoice;
    }
}
