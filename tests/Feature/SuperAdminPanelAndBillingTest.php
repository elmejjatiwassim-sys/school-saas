<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\SchoolSubscriptionStatus;
use App\Enums\StudentStatus;
use App\Filament\SuperAdmin\Resources\SchoolResource\Pages\ListSchools;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\PlatformSubscriptionInvoice;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\PlatformBillingService;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminPanelAndBillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $regularAdmin;

    protected School $schoolA;

    protected School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'username' => 'SUPER-0001',
            'email' => 'superadmin@platform.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $this->schoolA = School::create([
            'name' => 'Al-Amal School',
            'slug' => 'al-amal',
            'code' => 'AMAL',
            'email' => 'contact@alamal.ma',
            'phone' => '0522112233',
            'city' => 'Casablanca',
            'logo_path' => 'schools/logos/alamal.png',
            'favicon_path' => 'schools/favicons/alamal.ico',
            'price_per_student' => 2.00,
            'monthly_minimum_charge' => 400.00,
            'subscription_status' => SchoolSubscriptionStatus::Active,
            'stripe_customer_id' => 'cus_test_amal_123',
            'is_active' => true,
        ]);

        $this->regularAdmin = User::create([
            'name' => 'School Admin',
            'username' => 'AMAL-1001',
            'email' => 'admin@alamal.ma',
            'password' => Hash::make('password'),
            'school_id' => $this->schoolA->id,
            'role' => 'admin',
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        $this->schoolB = School::create([
            'name' => 'Lycée Descartes',
            'slug' => 'lycee-descartes',
            'code' => 'DESC',
            'email' => 'contact@descartes.ma',
            'phone' => '0537112233',
            'city' => 'Rabat',
            'price_per_student' => 1.50,
            'monthly_minimum_charge' => 300.00,
            'subscription_status' => SchoolSubscriptionStatus::Active,
            'stripe_customer_id' => null,
            'is_active' => true,
        ]);

        $academicYearA = AcademicYear::create([
            'school_id' => $this->schoolA->id,
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);
        $gradeLevelA = GradeLevel::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Level 1',
            'code' => 'L1',
        ]);
        $this->classroomA = Classroom::create([
            'school_id' => $this->schoolA->id,
            'academic_year_id' => $academicYearA->id,
            'grade_level_id' => $gradeLevelA->id,
            'name' => 'CE1',
        ]);

        $academicYearB = AcademicYear::create([
            'school_id' => $this->schoolB->id,
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);
        $gradeLevelB = GradeLevel::create([
            'school_id' => $this->schoolB->id,
            'name' => 'Level 1',
            'code' => 'L1',
        ]);
        $this->classroomB = Classroom::create([
            'school_id' => $this->schoolB->id,
            'academic_year_id' => $academicYearB->id,
            'grade_level_id' => $gradeLevelB->id,
            'name' => 'CP',
        ]);
    }

    protected Classroom $classroomA;

    protected Classroom $classroomB;

    public function test_super_admin_panel_access_control(): void
    {
        // 1. Guest is redirected
        $response = $this->get('/super-admin');
        $response->assertRedirect('/super-admin/login');

        // 2. Regular school admin without super_admin flag gets 403 Forbidden
        $response = $this->actingAs($this->regularAdmin)->get('/super-admin');
        $response->assertStatus(403);

        // 3. User with is_super_admin = true gets 200 OK
        $response = $this->actingAs($this->superAdmin)->get('/super-admin');
        $response->assertStatus(200);
    }

    public function test_dynamic_branding_resolves_tenant_brand_name_logo_and_favicon(): void
    {
        $this->actingAs($this->regularAdmin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->schoolA);

        $panel = Filament::getCurrentPanel();

        // 1. Brand name resolves tenant school name
        $this->assertEquals('Al-Amal School', $panel->getBrandName());

        // 2. Brand logo resolves tenant school logo URL
        $this->assertEquals(asset('storage/schools/logos/alamal.png'), $panel->getBrandLogo());

        // 3. Favicon resolves tenant school favicon URL
        $this->assertEquals(asset('storage/schools/favicons/alamal.ico'), $panel->getFavicon());

        // Test fallback when school has no logo or favicon
        $regularAdminB = User::create([
            'name' => 'Admin B',
            'username' => 'DESC-1001',
            'password' => Hash::make('pass'),
            'school_id' => $this->schoolB->id,
        ]);
        $this->actingAs($regularAdminB);
        Filament::setTenant($this->schoolB);
        $this->assertEquals('Lycée Descartes', $panel->getBrandName());
        $this->assertNull($panel->getBrandLogo());
        $this->assertNull($panel->getFavicon());
    }

    public function test_super_admin_can_view_all_schools_and_estimated_bills(): void
    {
        // Create active students in School A (5 students @ 2.00 MAD = 10 MAD -> Below minimum 400 MAD => Bill: 400.00)
        for ($i = 1; $i <= 5; $i++) {
            Student::create([
                'school_id' => $this->schoolA->id,
                'classroom_id' => $this->classroomA->id,
                'first_name' => "Student {$i}",
                'last_name' => 'A',
                'gender' => Gender::Male,
                'status' => StudentStatus::Active,
            ]);
        }

        // 5 active students * 2.00 MAD = 10 MAD -> Minimum is 400.00 MAD => Bill: 400.00 MAD
        $this->assertEquals(5, $this->schoolA->active_students_count);
        $this->assertEquals(400.00, $this->schoolA->estimated_monthly_bill);

        // For School B: 250 active students * 1.50 MAD = 375.00 MAD -> Above minimum 300.00 MAD => Bill: 375.00 MAD
        for ($i = 1; $i <= 250; $i++) {
            Student::create([
                'school_id' => $this->schoolB->id,
                'classroom_id' => $this->classroomB->id,
                'first_name' => "Student {$i}",
                'last_name' => 'B',
                'gender' => Gender::Male,
                'status' => StudentStatus::Active,
            ]);
        }

        $this->assertEquals(250, $this->schoolB->active_students_count);
        $this->assertEquals(375.00, $this->schoolB->estimated_monthly_bill);

        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));

        Livewire::test(ListSchools::class)
            ->assertSuccessful()
            ->assertSee($this->schoolA->name)
            ->assertSee($this->schoolB->name)
            ->assertSee('AMAL')
            ->assertSee('DESC');
    }

    public function test_platform_billing_service_calculates_and_charges_subscriptions(): void
    {
        // Add 10 active students to School A (10 * 2.00 = 20 MAD -> minimum applies = 400.00 MAD)
        for ($i = 1; $i <= 10; $i++) {
            Student::create([
                'school_id' => $this->schoolA->id,
                'classroom_id' => $this->classroomA->id,
                'first_name' => "Student {$i}",
                'last_name' => 'A',
                'gender' => Gender::Male,
                'status' => StudentStatus::Active,
            ]);
        }

        $service = app(PlatformBillingService::class);
        $invoice = $service->billSchoolForMonth($this->schoolA, '2026-09');

        $this->assertInstanceOf(PlatformSubscriptionInvoice::class, $invoice);
        $this->assertEquals($this->schoolA->id, $invoice->school_id);
        $this->assertEquals('2026-09', $invoice->billing_month);
        $this->assertEquals(10, $invoice->active_students_count);
        $this->assertEquals(2.00, (float) $invoice->rate_applied);
        $this->assertEquals(400.00, (float) $invoice->total_amount); // minimum charge applied

        // School A has stripe_customer_id, so it was charged and marked as paid
        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertNotNull($invoice->stripe_invoice_id);

        // School B has no stripe customer id, so it remains unpaid
        $invoiceB = $service->billSchoolForMonth($this->schoolB, '2026-09');
        $this->assertEquals('unpaid', $invoiceB->status);
        $this->assertNull($invoiceB->paid_at);
        $this->assertNull($invoiceB->stripe_invoice_id);
        $this->assertEquals(300.00, (float) $invoiceB->total_amount); // minimum 300 MAD
    }

    public function test_bill_schools_monthly_artisan_command_skips_summer_break(): void
    {
        // July (Month 7)
        $this->artisan('app:bill-schools-monthly', ['--month' => 7, '--year' => 2026])
            ->expectsOutputToContain('summer break')
            ->assertSuccessful();

        // August (Month 8)
        $this->artisan('app:bill-schools-monthly', ['--month' => 8, '--year' => 2026])
            ->expectsOutputToContain('summer break')
            ->assertSuccessful();

        $this->assertEquals(0, PlatformSubscriptionInvoice::count());
    }

    public function test_bill_schools_monthly_artisan_command_executes_for_academic_months(): void
    {
        $this->artisan('app:bill-schools-monthly', ['--month' => 9, '--year' => 2026])
            ->expectsOutputToContain('Successfully processed platform billing for 2 schools for 2026-09.')
            ->assertSuccessful();

        $this->assertEquals(2, PlatformSubscriptionInvoice::where('billing_month', '2026-09')->count());
    }

    public function test_routes_console_registers_platform_billing_schedule(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $billingEvent = $events->first(fn ($e) => str_contains($e->command ?? '', 'app:bill-schools-monthly'));

        $this->assertNotNull($billingEvent, 'Schedule for app:bill-schools-monthly not found.');
        $this->assertEquals('0 0 1 * *', $billingEvent->expression); // 1st of month at midnight
    }
}
