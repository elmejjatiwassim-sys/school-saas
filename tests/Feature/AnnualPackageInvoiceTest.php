<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceGenerationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnualPackageInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_package_invoice_and_line_items_creation(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        $service = app(InvoiceGenerationService::class);
        $invoice = $service->generateAnnualPackage(
            student: $student,
            academicYear: $academicYear,
            monthlyTuition: 1500.00,
            registrationFee: 1000.00,
            insuranceFee: 300.00,
            dueDate: '2026-09-05'
        );

        $this->assertEquals(InvoiceType::AnnualPackage, $invoice->type);
        $this->assertNull($invoice->billing_month);
        $this->assertEquals(2026, $invoice->billing_year);
        $this->assertEquals(16300.00, (float) $invoice->total_amount);
        $this->assertEquals(InvoiceStatus::Unpaid, $invoice->status);

        $this->assertCount(3, $invoice->items);

        $tuitionItem = $invoice->items->firstWhere('quantity', 10);
        $this->assertNotNull($tuitionItem);
        $this->assertEquals(1500.00, (float) $tuitionItem->amount);
        $this->assertEquals(15000.00, $tuitionItem->subtotal);

        $regItem = $invoice->items->firstWhere('amount', 1000.00);
        $this->assertNotNull($regItem);
        $this->assertEquals(1, $regItem->quantity);

        $insItem = $invoice->items->firstWhere('amount', 300.00);
        $this->assertNotNull($insItem);
        $this->assertEquals(1, $insItem->quantity);
    }

    public function test_paying_annual_package_automatically_settles_all_10_individual_monthly_invoices(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        $service = app(InvoiceGenerationService::class);
        $annualInvoice = $service->generateAnnualPackage(
            student: $student,
            academicYear: $academicYear,
            monthlyTuition: 1600.00,
            registrationFee: 1200.00,
            insuranceFee: 400.00
        );

        $this->assertEquals(17600.00, (float) $annualInvoice->total_amount);

        // Before payment: only 1 invoice exists (the annual package)
        $this->assertEquals(1, Invoice::where('student_id', $student->id)->count());

        // Now record full payment for the annual package invoice
        Payment::create([
            'school_id' => $school->id,
            'invoice_id' => $annualInvoice->id,
            'amount' => 17600.00,
            'payment_date' => '2026-09-02',
            'payment_method' => PaymentMethod::BankTransfer,
            'receipt_number' => 'REC-ANN-2026-001',
            'notes' => 'Full annual package paid via bank wire',
        ]);

        $annualInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $annualInvoice->status);
        $this->assertEquals(17600.00, (float) $annualInvoice->paid_amount);

        // After payment: 11 invoices exist (1 annual + 10 monthly invoices)
        $monthlyInvoices = Invoice::where('student_id', $student->id)
            ->where('type', InvoiceType::Monthly)
            ->get();

        $this->assertCount(10, $monthlyInvoices);

        $expectedMonths = [9, 10, 11, 12, 1, 2, 3, 4, 5, 6];
        $actualMonths = $monthlyInvoices->pluck('billing_month')->all();
        sort($actualMonths);
        sort($expectedMonths);
        $this->assertEquals($expectedMonths, $actualMonths);

        foreach ($monthlyInvoices as $mInvoice) {
            $this->assertEquals(InvoiceStatus::Paid, $mInvoice->status);
            $this->assertEquals(1600.00, (float) $mInvoice->total_amount);
            $this->assertEquals(1600.00, (float) $mInvoice->paid_amount);

            // Assert settlement payment references master invoice
            $payment = $mInvoice->payments()->first();
            $this->assertNotNull($payment);
            $this->assertEquals(1600.00, (float) $payment->amount);
            $this->assertStringContainsString($annualInvoice->invoice_number, $payment->notes);
        }
    }

    public function test_paying_annual_package_settles_pre_existing_unpaid_monthly_invoices(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        // Pre-create month 9 invoice
        $sepInvoice = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'invoice_number' => 'INV-202609-001',
            'type' => InvoiceType::Monthly,
            'billing_month' => 9,
            'billing_year' => 2026,
            'title' => 'واجب شهر شتنبر 2026',
            'total_amount' => 1500.00,
            'paid_amount' => 0.00,
            'due_date' => '2026-09-05',
            'status' => InvoiceStatus::Unpaid,
        ]);

        $service = app(InvoiceGenerationService::class);
        $annualInvoice = $service->generateAnnualPackage(
            student: $student,
            academicYear: $academicYear,
            monthlyTuition: 1500.00,
            registrationFee: 1000.00,
            insuranceFee: 200.00
        );

        // Pay annual package
        Payment::create([
            'school_id' => $school->id,
            'invoice_id' => $annualInvoice->id,
            'amount' => 16200.00,
            'payment_date' => '2026-09-03',
            'payment_method' => PaymentMethod::Cash,
            'receipt_number' => 'REC-ANN-2026-CASH',
        ]);

        $sepInvoice->refresh();
        $this->assertEquals(InvoiceStatus::Paid, $sepInvoice->status);
        $this->assertEquals(1500.00, (float) $sepInvoice->paid_amount);

        // Total 10 monthly invoices exist
        $this->assertEquals(10, Invoice::where('student_id', $student->id)->where('type', InvoiceType::Monthly)->count());
    }

    public function test_detailed_receipt_displays_line_items_breakdown(): void
    {
        $school = School::factory()->create(['slug' => 'excellence-school']);
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'name' => '2026-2027',
        ]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        $service = app(InvoiceGenerationService::class);
        $annualInvoice = $service->generateAnnualPackage(
            student: $student,
            academicYear: $academicYear,
            monthlyTuition: 1500.00,
            registrationFee: 1000.00,
            insuranceFee: 250.00
        );

        $payment = Payment::create([
            'school_id' => $school->id,
            'invoice_id' => $annualInvoice->id,
            'amount' => 16250.00,
            'payment_date' => '2026-09-04',
            'payment_method' => PaymentMethod::Cash,
            'receipt_number' => 'REC-ANN-101',
        ]);

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/payments/{$payment->id}/receipt")
            ->assertSuccessful()
            ->assertSee('Invoice Items')
            ->assertSee('Monthly Tuition')
            ->assertSee('Registration Fee')
            ->assertSee('Insurance Fee')
            ->assertSee('16,250.00 MAD');
    }

    public function test_generate_annual_package_via_list_invoices_action(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        $this->actingAs($user);
        Filament::setTenant($school);

        Livewire::test(ListInvoices::class)
            ->callAction('generate_annual_package', [
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'monthly_tuition' => 1400.00,
                'registration_fee' => 900.00,
                'insurance_fee' => 200.00,
                'due_date' => '2026-09-05',
            ])
            ->assertHasNoActionErrors();

        $invoice = Invoice::where('student_id', $student->id)
            ->where('type', InvoiceType::AnnualPackage)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(15100.00, (float) $invoice->total_amount); // 14000 + 900 + 200
        $this->assertCount(3, $invoice->items);
    }
}
