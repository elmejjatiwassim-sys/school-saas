<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PublicPaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_token_is_automatically_generated_on_invoice_creation(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        $invoice = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'invoice_number' => 'INV-TEST-001',
            'billing_month' => 9,
            'billing_year' => 2026,
            'title' => 'Tuition Sept 2026',
            'total_amount' => 1500.00,
            'due_date' => '2026-09-05',
        ]);

        $this->assertNotNull($invoice->payment_token);
        $this->assertTrue(Str::isUuid($invoice->payment_token));
    }

    public function test_public_payment_route_renders_teaser_page_for_valid_token(): void
    {
        $school = School::factory()->create(['name' => 'Al Amal Academy']);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'CM2-A']);
        $student = Student::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'first_name' => 'Mehdi',
            'last_name' => 'Alami',
        ]);

        $invoice = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'invoice_number' => 'INV-PUB-2026-001',
            'billing_month' => 10,
            'billing_year' => 2026,
            'title' => 'واجب شهر أكتوبر 2026',
            'total_amount' => 1800.00,
            'due_date' => '2026-10-05',
        ]);

        $response = $this->get("/pay/invoice/{$invoice->payment_token}");

        $response->assertSuccessful();
        $response->assertSee('بوابة الأداء الإلكتروني قيد التفعيل حالياً. يرجى التواصل مع إدارة المؤسسة لأداء الواجبات.');
        $response->assertSee('Al Amal Academy');
        $response->assertSee('Mehdi Alami');
        $response->assertSee('1,800.00');
        $response->assertSee('Coming Soon');
    }

    public function test_public_payment_route_aborts_404_for_invalid_token(): void
    {
        $response = $this->get('/pay/invoice/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    public function test_filament_invoice_resource_has_online_payment_link_action(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        $invoice = Invoice::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'invoice_number' => 'INV-TEST-FILAMENT-01',
            'billing_month' => 9,
            'billing_year' => 2026,
            'title' => 'Tuition Test',
            'total_amount' => 1500.00,
            'due_date' => '2026-09-05',
        ]);

        $this->actingAs($user);
        Filament::setTenant($school);

        Livewire::test(ListInvoices::class)
            ->assertTableActionExists('online_payment_link')
            ->mountTableAction('online_payment_link', $invoice)
            ->assertTableActionMounted('online_payment_link')
            ->assertSee('بوابة الأداء الإلكتروني (قريباً / Coming Soon)')
            ->assertSee('Coming Soon');
    }
}
