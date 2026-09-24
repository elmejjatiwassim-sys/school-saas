<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\PaymentResource\Pages\CreatePayment;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomTuitionAndGuardianInvoicesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_monthly_tuition_fee_persists_in_database(): void
    {
        $school = School::factory()->create();
        $student = Student::factory()->create([
            'school_id' => $school->id,
            'monthly_tuition_fee' => 1450.50,
        ]);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'monthly_tuition_fee' => 1450.50,
        ]);

        $student->refresh();
        $this->assertEquals(1450.50, (float) $student->monthly_tuition_fee);
    }

    public function test_payment_resource_autofills_amount_with_student_monthly_tuition_fee(): void
    {
        $school = School::factory()->create(['slug' => 'maroc-excellence']);
        $user = User::factory()->create(['school_id' => $school->id, 'role' => 'admin']);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $guardian = Guardian::factory()->create(['school_id' => $school->id]);

        $student = Student::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'guardian_id' => $guardian->id,
            'first_name' => 'Amine',
            'last_name' => 'Tazi',
            'monthly_tuition_fee' => 1600.00,
        ]);

        $invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'total_amount' => 1600.00,
            'paid_amount' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $this->actingAs($user);
        Filament::setTenant($school);

        // Test Livewire CreatePayment form interaction
        Livewire::test(CreatePayment::class)
            ->assertSuccessful()
            ->set('data.student_id', $student->id)
            ->assertSet('data.amount', 1600.00)
            ->assertSet('data.invoice_id', $invoice->id);
    }

    public function test_payment_resource_supports_cheque_payment_method_with_cheque_details(): void
    {
        $school = School::factory()->create(['slug' => 'maroc-excellence-2']);
        $user = User::factory()->create(['school_id' => $school->id, 'role' => 'admin']);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $guardian = Guardian::factory()->create(['school_id' => $school->id]);

        $student = Student::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'guardian_id' => $guardian->id,
            'monthly_tuition_fee' => 2000.00,
        ]);

        $invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'total_amount' => 2000.00,
            'paid_amount' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $this->actingAs($user);
        Filament::setTenant($school);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'student_id' => $student->id,
                'invoice_id' => $invoice->id,
                'amount' => 2000.00,
                'payment_date' => now()->toDateString(),
                'payment_method' => PaymentMethod::Cheque->value,
                'cheque_number' => 'CHQ-2026-8899',
                'bank_name' => 'Attijariwafa Bank',
                'receipt_number' => 'REC-CHQ-001',
                'notes' => 'Paiement par chèque certifié',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('payments', [
            'school_id' => $school->id,
            'invoice_id' => $invoice->id,
            'amount' => 2000.00,
            'payment_method' => 'cheque',
            'cheque_number' => 'CHQ-2026-8899',
            'bank_name' => 'Attijariwafa Bank',
            'receipt_number' => 'REC-CHQ-001',
        ]);
    }

    public function test_receipt_view_displays_school_stamp_under_la_direction_and_omits_guardian_signature(): void
    {
        $school = School::factory()->create([
            'slug' => 'ecole-royale',
            'name' => 'École Royale',
            'phone' => '+212537112233',
            'stamp_signature_path' => 'schools/stamps/direction_stamp.png',
        ]);

        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $guardian = Guardian::factory()->create(['school_id' => $school->id]);
        $student = Student::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'guardian_id' => $guardian->id,
        ]);
        $feeType = FeeType::factory()->create(['school_id' => $school->id]);

        $invoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'total_amount' => 1800.00,
            'paid_amount' => 1800.00,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'fee_type_id' => $feeType->id,
            'description' => 'Scolarité mensuelle',
            'amount' => 1800.00,
            'quantity' => 1,
        ]);

        $payment = Payment::factory()->create([
            'school_id' => $school->id,
            'invoice_id' => $invoice->id,
            'amount' => 1800.00,
            'payment_method' => PaymentMethod::Cheque,
            'cheque_number' => 'CHQ-ROYAL-77',
            'bank_name' => 'Banque Populaire',
            'receipt_number' => 'REC-ROYAL-001',
        ]);

        $response = $this->actingAs($user)->get("/admin/{$school->slug}/payments/{$payment->id}/receipt");
        $response->assertSuccessful();

        $content = $response->getContent();

        // 1. Verify "إدارة المؤسسة / La Direction" is present
        $this->assertStringContainsString('إدارة المؤسسة / La Direction', $content);

        // 2. Verify school stamp image path is rendered
        $this->assertStringContainsString('direction_stamp.png', $content);

        // 3. Verify Cheque details are rendered
        $this->assertStringContainsString('CHQ-ROYAL-77', $content);
        $this->assertStringContainsString('Banque Populaire', $content);

        // 4. Verify Guardian signature block is completely removed
        $this->assertStringNotContainsString('Signature du Tuteur', $content);
        $this->assertStringNotContainsString('توقيع ولي الأمر', $content);

        // 5. Verify PDF download and Share buttons exist
        $this->assertStringContainsString('Download PDF / تحميل PDF', $content);
        $this->assertStringContainsString('Share / مشاركة', $content);

        // 6. Test downloading the PDF directly via format=pdf
        $pdfResponse = $this->actingAs($user)->get("/admin/{$school->slug}/payments/{$payment->id}/receipt?format=pdf");
        $pdfResponse->assertSuccessful();
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('content-type'));
    }

    public function test_guardian_mobile_api_returns_invoices_with_receipt_download_url(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => '6ème A']);
        $guardian = Guardian::factory()->create(['school_id' => $school->id]);

        $student = Student::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'guardian_id' => $guardian->id,
            'monthly_tuition_fee' => 1500.00,
        ]);

        $feeType = FeeType::factory()->create(['school_id' => $school->id]);

        // Invoice 1: Paid invoice
        $paidInvoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'title' => 'Frais Scolaires Septembre',
            'total_amount' => 1500.00,
            'paid_amount' => 1500.00,
            'status' => InvoiceStatus::Paid,
            'due_date' => '2026-09-05',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $paidInvoice->id,
            'fee_type_id' => $feeType->id,
            'description' => 'Frais Septembre',
            'amount' => 1500.00,
            'quantity' => 1,
        ]);

        $payment = Payment::factory()->create([
            'school_id' => $school->id,
            'invoice_id' => $paidInvoice->id,
            'amount' => 1500.00,
            'payment_method' => PaymentMethod::Cash,
            'receipt_number' => 'REC-API-PAID-01',
        ]);

        // Invoice 2: Pending/Unpaid invoice
        $unpaidInvoice = Invoice::factory()->create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'title' => 'Frais Scolaires Octobre',
            'total_amount' => 1500.00,
            'paid_amount' => 0.00,
            'status' => InvoiceStatus::Unpaid,
            'due_date' => '2026-10-05',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $unpaidInvoice->id,
            'fee_type_id' => $feeType->id,
            'description' => 'Frais Octobre',
            'amount' => 1500.00,
            'quantity' => 1,
        ]);

        // Test API Endpoint: GET /api/v1/guardian/students/{student_id}/invoices
        $response = $this->getJson("/api/v1/guardian/students/{$student->id}/invoices");

        $response->assertSuccessful()
            ->assertJson([
                'success' => true,
                'student' => [
                    'id' => $student->id,
                    'monthly_tuition_fee' => 1500.00,
                ],
                'summary' => [
                    'total_invoices' => 2,
                    'paid_count' => 1,
                    'pending_count' => 1,
                ],
            ]);

        $responseData = $response->json();

        // 1. Verify paid invoices list
        $this->assertCount(1, $responseData['paid_invoices']);
        $paidItem = $responseData['paid_invoices'][0];
        $this->assertEquals($paidInvoice->id, $paidItem['id']);
        $this->assertTrue($paidItem['is_paid']);
        $this->assertNotEmpty($paidItem['receipt_download_url']);
        $this->assertEquals('REC-API-PAID-01', $paidItem['receipt_number']);

        // 2. Verify pending invoices list
        $this->assertCount(1, $responseData['pending_invoices']);
        $pendingItem = $responseData['pending_invoices'][0];
        $this->assertEquals($unpaidInvoice->id, $pendingItem['id']);
        $this->assertFalse($pendingItem['is_paid']);

        // 3. Test downloading the receipt PDF via the provided receipt_download_url
        $downloadResponse = $this->get($paidItem['receipt_download_url']);
        $downloadResponse->assertSuccessful();
        $this->assertEquals('application/pdf', $downloadResponse->headers->get('content-type'));
    }
}
