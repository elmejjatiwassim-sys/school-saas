<?php

namespace Tests\Feature;

use App\Enums\AiActionLogStatus;
use App\Enums\PaymentMethod;
use App\Filament\Pages\AiCopilot;
use App\Models\AcademicYear;
use App\Models\AiActionLog;
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

class AdminLanguageSwitcherAndReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_copilot_action_confirmation_button_and_execution_flow(): void
    {
        $school = School::factory()->create(['slug' => 'excellence-academy']);
        $user = User::factory()->create(['school_id' => $school->id, 'role' => 'admin']);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'name' => 'CP-A',
        ]);

        $this->actingAs($user);
        Filament::setTenant($school);

        $livewire = Livewire::test(AiCopilot::class)
            ->assertSuccessful()
            ->set('prompt', 'Register student Salma Benani in CP-A phone 0611223344')
            ->call('sendMessage');

        $log = AiActionLog::where('school_id', $school->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(AiActionLogStatus::PendingConfirmation, $log->status);

        // Verify the confirmation button text is visible in the view
        $livewire->assertSee('تأكيد العملية / Confirm Action')
            ->assertSee('wire:click="confirmAction"', false);

        // Call confirmAction without arguments (simulating wire:click="confirmAction")
        $livewire->call('confirmAction')
            ->assertHasNoErrors()
            ->assertNotified('Action Executed (تم التنفيذ بنجاح)');

        $log->refresh();
        $this->assertEquals(AiActionLogStatus::Executed, $log->status);
        $this->assertNotNull($log->confirmed_at);

        $this->assertDatabaseHas('students', [
            'school_id' => $school->id,
            'first_name' => 'Salma',
            'last_name' => 'Benani',
        ]);
    }

    public function test_language_switcher_persists_in_session_and_user_profile(): void
    {
        $school = School::factory()->create(['slug' => 'atlas-school']);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'locale' => 'ar',
        ]);

        // 1. Switch to French
        $response = $this->actingAs($user)->get('/locale/fr');
        $response->assertRedirect();
        $this->assertEquals('fr', session('locale'));
        $this->assertEquals('fr', $user->fresh()->locale);

        // Verify panel request resolves French locale
        $this->get("/admin/{$school->slug}")
            ->assertSuccessful();
        $this->assertEquals('fr', app()->getLocale());

        // 2. Switch to English
        $response = $this->actingAs($user)->get('/locale/en');
        $response->assertRedirect();
        $this->assertEquals('en', session('locale'));
        $this->assertEquals('en', $user->fresh()->locale);

        $this->get("/admin/{$school->slug}")
            ->assertSuccessful();
        $this->assertEquals('en', app()->getLocale());

        // 3. Switch to Arabic (RTL)
        $response = $this->actingAs($user)->get('/locale/ar');
        $response->assertRedirect();
        $this->assertEquals('ar', session('locale'));
        $this->assertEquals('ar', $user->fresh()->locale);

        $this->get("/admin/{$school->slug}")
            ->assertSuccessful();
        $this->assertEquals('ar', app()->getLocale());
        $this->assertEquals('rtl', __('filament-panels::layout.direction'));

        // 4. Invalid locale falls back safely
        $this->actingAs($user)->get('/locale/invalid-locale');
        $this->assertEquals('ar', session('locale'));
    }

    public function test_admin_panel_topbar_renders_language_switcher_options(): void
    {
        $school = School::factory()->create(['slug' => 'elite-school']);
        $user = User::factory()->create([
            'school_id' => $school->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->get("/admin/{$school->slug}");
        $response->assertSuccessful();
        $response->assertSee('Switch Language / تغيير اللغة');
        $response->assertSee('العربية');
        $response->assertSee('Français');
        $response->assertSee('English');
    }

    public function test_receipt_header_displays_phone_and_appends_email_when_both_present(): void
    {
        $school = School::factory()->create([
            'slug' => 'school-contact-full',
            'name' => 'Lycée Ibn Khaldoun',
            'phone' => '+212522112233',
            'email' => 'contact@ibnkhaldoun.ma',
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
            'total_amount' => 1500,
            'paid_amount' => 1500,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'fee_type_id' => $feeType->id,
            'description' => 'Frais de scolarité',
            'amount' => 1500,
            'quantity' => 1,
        ]);

        $payment = Payment::factory()->create([
            'school_id' => $school->id,
            'invoice_id' => $invoice->id,
            'amount' => 1500,
            'payment_method' => PaymentMethod::Cash,
            'receipt_number' => 'REC-FULL-001',
        ]);

        $response = $this->actingAs($user)->get("/admin/{$school->slug}/payments/{$payment->id}/receipt");
        $response->assertSuccessful();

        $response->assertSee('Tel: +212522112233');
        $response->assertSee('• <span>Email: contact@ibnkhaldoun.ma</span>', false);
    }

    public function test_receipt_header_hides_email_and_bullet_completely_when_email_is_null_or_empty(): void
    {
        $school = School::factory()->create([
            'slug' => 'school-contact-phone-only',
            'name' => 'École Al Amal',
            'phone' => '+212661009988',
            'email' => null,
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
            'total_amount' => 1000,
            'paid_amount' => 1000,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'fee_type_id' => $feeType->id,
            'description' => 'Frais mensuels',
            'amount' => 1000,
            'quantity' => 1,
        ]);

        $payment = Payment::factory()->create([
            'school_id' => $school->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'payment_method' => PaymentMethod::BankTransfer,
            'receipt_number' => 'REC-PHONE-001',
        ]);

        $response = $this->actingAs($user)->get("/admin/{$school->slug}/payments/{$payment->id}/receipt");
        $response->assertSuccessful();

        $response->assertSee('Tel: +212661009988');

        // Verify Email label and bullet are NOT rendered in the response
        $content = $response->getContent();
        $this->assertStringNotContainsString('Email:', $content);
        $this->assertStringNotContainsString('•', $content);
        $this->assertStringNotContainsString('&bull;', $content);
    }
}
