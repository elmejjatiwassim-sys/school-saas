<?php

namespace Tests\Feature;

use App\Enums\FeeInvoiceType;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\StudentStatus;
use App\Filament\Resources\StudentResource\Pages\CreateStudent;
use App\Filament\SuperAdmin\Resources\SchoolResource\Pages\EditSchool;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\AiStudentParserService;
use App\Services\InvoiceGenerationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MidYearOnboardingAndOpeningBalancesTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $academicYear;

    protected Classroom $classroom;

    protected User $tenantAdmin;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Groupe Scolaire Al Majd',
            'code' => 'MAJD',
            'slug' => 'al-majd',
            'email' => 'contact@almajd.ma',
            'billing_start_date' => '2027-01-15', // Onboarded mid-year in January 2027
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'school_id' => $this->school->id,
            'name' => 'Primaire',
            'code' => 'PRI',
        ]);

        $this->classroom = Classroom::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => '6ème Année Primaire',
            'capacity' => 30,
        ]);

        $this->tenantAdmin = User::create([
            'school_id' => $this->school->id,
            'name' => 'Admin Majd',
            'username' => 'MAJD-0001',
            'email' => 'admin@almajd.ma',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'school_id' => null,
            'name' => 'Super Administrator',
            'username' => 'SUPER-0001',
            'email' => 'super@platform.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
            'is_active' => true,
        ]);
    }

    public function test_school_and_student_and_invoice_support_new_schema_attributes(): void
    {
        $newSchool = School::create([
            'name' => 'École Nouvelle',
        ]);

        $this->assertNotNull($newSchool->billing_start_date);
        $this->assertEquals(now()->toDateString(), $newSchool->billing_start_date->toDateString());

        $student = Student::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
            'first_name' => 'Karim',
            'last_name' => 'Bennani',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        $this->assertEquals(0.00, (float) $student->opening_balance);

        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'invoice_number' => 'INV-TEST-001',
            'billing_year' => 2026,
            'billing_month' => 9,
            'title' => 'Test Invoice',
            'total_amount' => 1000.00,
            'due_date' => now()->toDateString(),
            'status' => InvoiceStatus::Unpaid,
        ]);

        $this->assertEquals(FeeInvoiceType::MonthlyFee, $invoice->invoice_type);
    }

    public function test_mid_year_onboarding_does_not_generate_invoices_for_past_months(): void
    {
        // School billing starts on 2027-01-15 (Mid-Year)
        // Academic cycle 2026-2027 has months: 9, 10, 11, 12 (2026) and 1, 2, 3, 4, 5, 6 (2027)
        // Past months: Sept, Oct, Nov, Dec 2026 (4 months) must NOT be generated!
        // Active months: Jan, Feb, Mar, Apr, May, Jun 2027 (6 months) MUST be generated!

        $student = Student::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
            'first_name' => 'Sara',
            'last_name' => 'Tahiri',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
            'opening_balance' => 0.00,
        ]);

        $service = app(InvoiceGenerationService::class);
        $count = $service->generateForStudent($student, $this->academicYear, 1200.00);

        $this->assertEquals(6, $count);
        $this->assertEquals(6, Invoice::where('student_id', $student->id)->count());

        // Verify past months (Sept to Dec 2026) do NOT exist
        foreach ([9, 10, 11, 12] as $pastMonth) {
            $this->assertDatabaseMissing('invoices', [
                'student_id' => $student->id,
                'billing_year' => 2026,
                'billing_month' => $pastMonth,
            ]);
        }

        // Verify onboarded months (Jan to Jun 2027) DO exist
        foreach ([1, 2, 3, 4, 5, 6] as $activeMonth) {
            $this->assertDatabaseHas('invoices', [
                'student_id' => $student->id,
                'billing_year' => 2027,
                'billing_month' => $activeMonth,
                'invoice_type' => FeeInvoiceType::MonthlyFee->value,
                'total_amount' => 1200.00,
            ]);
        }
    }

    public function test_student_created_with_opening_balance_automatically_generates_pending_opening_balance_invoice(): void
    {
        $student = Student::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
            'first_name' => 'Amine',
            'last_name' => 'Chraibi',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'opening_balance' => 3500.00,
        ]);

        $invoice = Invoice::where('student_id', $student->id)
            ->where('invoice_type', FeeInvoiceType::OpeningBalance->value)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(3500.00, (float) $invoice->total_amount);
        $this->assertEquals(0.00, (float) $invoice->paid_amount);
        $this->assertEquals(now()->toDateString(), $invoice->due_date->toDateString());
        $this->assertEquals(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertNull($invoice->billing_month);

        // Verify line item was created
        $this->assertCount(1, $invoice->items);
        $this->assertEquals(3500.00, (float) $invoice->items->first()->amount);
    }

    public function test_ai_student_parser_service_recognizes_solde_column_and_creates_opening_balance_invoices(): void
    {
        $csvContent = implode("\n", [
            'first_name,last_name,gender,date_of_birth,solde',
            'Anas,Tazi,male,2014-05-10,1800.50',
            'Hiba,Kabbaj,female,2015-08-22,0.00',
            'Omar,Alami,male,2014-11-03,450.00',
        ]);

        Storage::disk('local')->put('test_students.csv', $csvContent);
        $filePath = Storage::disk('local')->path('test_students.csv');

        $parser = app(AiStudentParserService::class);
        $parsed = $parser->parseFile($filePath, 'text/csv');

        $this->assertCount(3, $parsed);
        $this->assertEquals(1800.50, $parsed[0]['opening_balance']);
        $this->assertEquals(0.00, $parsed[1]['opening_balance']);
        $this->assertEquals(450.00, $parsed[2]['opening_balance']);

        // Import into school and classroom
        $importedCount = $parser->importStudents($parsed, $this->school->id, $this->classroom->id);
        $this->assertEquals(3, $importedCount);

        // Check Anas Tazi has opening balance invoice
        $anas = Student::where('first_name', 'Anas')->where('last_name', 'Tazi')->first();
        $this->assertNotNull($anas);
        $this->assertEquals(1800.50, (float) $anas->opening_balance);

        $anasInvoice = Invoice::where('student_id', $anas->id)->where('invoice_type', FeeInvoiceType::OpeningBalance->value)->first();
        $this->assertNotNull($anasInvoice);
        $this->assertEquals(1800.50, (float) $anasInvoice->total_amount);

        // Check Hiba Kabbaj (0.00 balance) has NO opening balance invoice
        $hiba = Student::where('first_name', 'Hiba')->where('last_name', 'Kabbaj')->first();
        $this->assertNotNull($hiba);
        $this->assertEquals(0.00, (float) $hiba->opening_balance);
        $this->assertNull(Invoice::where('student_id', $hiba->id)->where('invoice_type', FeeInvoiceType::OpeningBalance->value)->first());

        // Check Omar Alami (450.00 balance) has opening balance invoice
        $omar = Student::where('first_name', 'Omar')->where('last_name', 'Alami')->first();
        $this->assertNotNull($omar);
        $omarInvoice = Invoice::where('student_id', $omar->id)->where('invoice_type', FeeInvoiceType::OpeningBalance->value)->first();
        $this->assertNotNull($omarInvoice);
        $this->assertEquals(450.00, (float) $omarInvoice->total_amount);

        @unlink($filePath);
    }

    public function test_artisan_generate_monthly_invoices_command_respects_billing_start_date(): void
    {
        // Student in School Al Majd (billing_start_date: 2027-01-15)
        $student = Student::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
            'first_name' => 'Nour',
            'last_name' => 'Mansouri',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);

        // Attempting to generate for October 2026 (prior to Jan 2027) should skip this school
        $this->artisan('app:generate-monthly-invoices', ['--month' => 10, '--year' => 2026])
            ->assertSuccessful();

        $this->assertEquals(0, Invoice::where('student_id', $student->id)->count());

        // Generating for February 2027 (after Jan 2027) will generate invoices
        $this->artisan('app:generate-monthly-invoices', ['--month' => 2, '--year' => 2027])
            ->assertSuccessful();

        $this->assertTrue(Invoice::where('student_id', $student->id)->where('billing_month', 2)->where('billing_year', 2027)->exists());
    }

    public function test_super_admin_school_resource_has_billing_start_date_field(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));

        Livewire::test(EditSchool::class, ['record' => $this->school->getRouteKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('billing_start_date');
    }

    public function test_student_resource_form_has_opening_balance_field(): void
    {
        $this->actingAs($this->tenantAdmin);
        Filament::setTenant($this->school);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateStudent::class)
            ->assertSuccessful()
            ->assertFormFieldExists('opening_balance');
    }
}
