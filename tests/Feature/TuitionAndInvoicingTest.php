<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\InvoiceGenerationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuitionAndInvoicingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_type_belongs_to_school(): void
    {
        $school = School::factory()->create();
        $feeType = FeeType::factory()->create([
            'school_id' => $school->id,
            'name' => 'Tuition Fee',
            'default_amount' => 1500.00,
            'is_recurring_monthly' => true,
        ]);

        $this->assertTrue($feeType->school->is($school));
        $this->assertTrue($school->feeTypes->contains($feeType));
    }

    public function test_invoice_belongs_to_school_student_and_academic_year(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $student = Student::factory()->create(['school_id' => $school->id]);

        $invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'total_amount' => 1500.00,
            'paid_amount' => 0.00,
        ]);

        $this->assertTrue($invoice->school->is($school));
        $this->assertTrue($invoice->student->is($student));
        $this->assertTrue($invoice->academicYear->is($academicYear));
        $this->assertEquals(1500.00, $invoice->remaining_amount);
    }

    public function test_payment_creation_and_deletion_recalculates_invoice_paid_amount_and_status(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $student = Student::factory()->create(['school_id' => $school->id]);

        $invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'total_amount' => 1500.00,
            'paid_amount' => 0.00,
            'status' => InvoiceStatus::Unpaid,
            'due_date' => now()->addDays(10),
        ]);

        $this->assertEquals(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertEquals(0, $invoice->paid_amount);

        // Make partial payment of 500
        $payment1 = Payment::create([
            'school_id' => $school->id,
            'invoice_id' => $invoice->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::Cash,
            'receipt_number' => 'REC-TEST-001',
        ]);

        $invoice->refresh();
        $this->assertEquals(500.00, $invoice->paid_amount);
        $this->assertEquals(1000.00, $invoice->remaining_amount);
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);

        // Make second payment of 1000 (total = 1500 -> fully paid)
        $payment2 = Payment::create([
            'school_id' => $school->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => PaymentMethod::BankTransfer,
            'receipt_number' => 'REC-TEST-002',
        ]);

        $invoice->refresh();
        $this->assertEquals(1500.00, $invoice->paid_amount);
        $this->assertEquals(0.00, $invoice->remaining_amount);
        $this->assertEquals(InvoiceStatus::Paid, $invoice->status);

        // Delete payment2 -> should return to partially_paid
        $payment2->delete();

        $invoice->refresh();
        $this->assertEquals(500.00, $invoice->paid_amount);
        $this->assertEquals(1000.00, $invoice->remaining_amount);
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $invoice->status);

        // Delete payment1 -> should return to unpaid
        $payment1->delete();

        $invoice->refresh();
        $this->assertEquals(0.00, $invoice->paid_amount);
        $this->assertEquals(InvoiceStatus::Unpaid, $invoice->status);
    }

    public function test_invoice_generation_service_creates_ten_months_cycle_skipping_july_and_august(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);
        $student = Student::factory()->create(['school_id' => $school->id]);

        $service = app(InvoiceGenerationService::class);
        $count = $service->generateForStudent($student, $academicYear, 1200.00, 5);

        $this->assertEquals(10, $count);

        $invoices = Invoice::where('student_id', $student->id)->orderBy('billing_year')->orderBy('billing_month')->get();
        $this->assertCount(10, $invoices);

        $months = $invoices->pluck('billing_month')->all();
        $this->assertEquals([1, 2, 3, 4, 5, 6, 9, 10, 11, 12], collect($months)->sort()->values()->all());

        // Verify July (7) and August (8) are skipped
        $this->assertNotContains(7, $months);
        $this->assertNotContains(8, $months);

        // Running service again should not duplicate invoices
        $secondRunCount = $service->generateForStudent($student, $academicYear, 1200.00, 5);
        $this->assertEquals(0, $secondRunCount);
        $this->assertEquals(10, Invoice::where('student_id', $student->id)->count());
    }

    public function test_invoice_generation_service_creates_invoices_for_entire_classroom(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);

        Student::factory()->count(3)->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'status' => 'active',
        ]);

        $service = app(InvoiceGenerationService::class);
        $totalCreated = $service->generateForClassroom($classroom, $academicYear, 1500.00);

        // 3 students * 10 months = 30 invoices
        $this->assertEquals(30, $totalCreated);
    }

    public function test_payment_receipt_route_is_accessible_and_isolated_by_tenant(): void
    {
        $school1 = School::factory()->create(['slug' => 'school-alpha']);
        $user1 = User::factory()->create(['school_id' => $school1->id]);

        $school2 = School::factory()->create(['slug' => 'school-beta']);

        $academicYear1 = AcademicYear::factory()->create(['school_id' => $school1->id]);
        $student1 = Student::factory()->create(['school_id' => $school1->id]);
        $invoice1 = Invoice::factory()->create([
            'school_id' => $school1->id,
            'student_id' => $student1->id,
            'academic_year_id' => $academicYear1->id,
        ]);
        $payment1 = Payment::factory()->create([
            'school_id' => $school1->id,
            'invoice_id' => $invoice1->id,
            'receipt_number' => 'REC-ALPHA-001',
        ]);

        // User from school1 accesses payment1 receipt
        $response = $this->actingAs($user1)->get("/admin/{$school1->slug}/payments/{$payment1->id}/receipt");
        $response->assertSuccessful();
        $response->assertSee('REC-ALPHA-001');
        $response->assertSee($school1->name);

        // Attempting to access school1's payment under school2 slug returns 404
        $responseForbidden = $this->actingAs($user1)->get("/admin/{$school2->slug}/payments/{$payment1->id}/receipt");
        $responseForbidden->assertStatus(404);
    }

    public function test_database_seeder_seeds_invoices_and_payments_for_months_nine_and_ten(): void
    {
        $this->seed(DatabaseSeeder::class);

        $school = School::where('slug', 'al-amal')->firstOrFail();

        $month9Invoices = Invoice::where('school_id', $school->id)->where('billing_month', 9)->get();
        $month10Invoices = Invoice::where('school_id', $school->id)->where('billing_month', 10)->get();

        $this->assertCount(5, $month9Invoices);
        $this->assertCount(5, $month10Invoices);

        $totalPayments = Payment::where('school_id', $school->id)->count();
        $this->assertGreaterThanOrEqual(5, $totalPayments);

        $paidInvoices = Invoice::where('school_id', $school->id)->where('status', InvoiceStatus::Paid)->count();
        $this->assertGreaterThanOrEqual(1, $paidInvoices);
    }
}
