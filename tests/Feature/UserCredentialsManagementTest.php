<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ManageCredentials;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\CredentialService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserCredentialsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Al-Najah Academy',
            'slug' => 'al-najah',
            'email' => 'admin@alnajah.ma',
            'phone' => '0522112233',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'School Admin',
            'username' => 'NAJA-1001',
            'email' => 'admin@alnajah.ma',
            'password' => Hash::make('secret123'),
            'temporary_password' => 'secret123',
            'school_id' => $this->school->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'school_id' => $this->school->id,
            'name' => '1st Grade',
            'code' => 'G1',
        ]);

        $academicYear = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);

        $this->classroom = Classroom::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'Class A',
            'capacity' => 30,
        ]);
    }

    public function test_school_auto_generates_unique_uppercase_code_on_creation(): void
    {
        $this->assertNotEmpty($this->school->code);
        $this->assertEquals(strtoupper($this->school->code), $this->school->code);
        $this->assertGreaterThanOrEqual(3, strlen($this->school->code));
        $this->assertLessThanOrEqual(4, strlen($this->school->code));

        // Create second school with same prefix, must generate a unique code
        $school2 = School::create([
            'name' => 'Al-Najah Academy 2',
            'slug' => 'al-najah-2',
        ]);

        $this->assertNotEquals($this->school->code, $school2->code);
        $this->assertEquals(strtoupper($school2->code), $school2->code);
    }

    public function test_credential_service_generates_unique_username_matching_school_code(): void
    {
        $service = app(CredentialService::class);
        $username = $service->generateUniqueUsername($this->school);

        $this->assertStringStartsWith($this->school->code.'-', $username);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3,4}-\d{4}$/', $username);
    }

    public function test_credential_service_creates_student_credentials(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Omar',
            'last_name' => 'Alami',
            'phone' => '0611223344',
            'relationship_type' => 'father',
        ]);

        $student = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $this->classroom->id,
            'first_name' => 'Youssef',
            'last_name' => 'Alami',
            'date_of_birth' => '2015-05-10',
            'gender' => 'male',
        ]);

        $this->assertNull($student->user_id);

        $service = app(CredentialService::class);
        $user = $service->createStudentCredential($student);

        $this->assertNotNull($user);
        $this->assertEquals('student', $user->role);
        $this->assertEquals($this->school->id, $user->school_id);
        $this->assertEquals($student->id, $user->student_id);
        $this->assertEquals($this->classroom->id, $user->classroom_id);
        $this->assertStringStartsWith($this->school->code.'-', $user->username);
        $this->assertNotEmpty($user->temporary_password);
        $this->assertTrue(Hash::check($user->temporary_password, $user->password));

        $student->refresh();
        $this->assertEquals($user->id, $student->user_id);
    }

    public function test_credential_service_resets_user_password(): void
    {
        $service = app(CredentialService::class);
        $oldPasswordHash = $this->admin->password;

        $newPassword = $service->resetPassword($this->admin);

        $this->admin->refresh();
        $this->assertEquals($newPassword, $this->admin->temporary_password);
        $this->assertTrue(Hash::check($newPassword, $this->admin->password));
        $this->assertNotEquals($oldPasswordHash, $this->admin->password);
    }

    public function test_bulk_generate_credentials_for_classroom(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Fatima',
            'last_name' => 'Zahra',
            'phone' => '0622334455',
            'relationship_type' => 'mother',
        ]);

        $students = collect();
        for ($i = 1; $i <= 3; $i++) {
            $students->push(Student::create([
                'school_id' => $this->school->id,
                'guardian_id' => $guardian->id,
                'classroom_id' => $this->classroom->id,
                'first_name' => "Student {$i}",
                'last_name' => 'Test',
                'date_of_birth' => '2016-01-01',
                'gender' => 'male',
            ]));
        }

        $service = app(CredentialService::class);
        $results = $service->bulkGenerateForClassroom($this->classroom);

        $this->assertCount(3, $results);

        foreach ($students as $student) {
            $student->refresh();
            $this->assertNotNull($student->user_id);
            $this->assertNotNull($student->user->username);
        }

        // Running again shouldn't duplicate
        $secondRun = $service->bulkGenerateForClassroom($this->classroom);
        $this->assertCount(0, $secondRun);
    }

    public function test_user_can_login_with_username_or_email(): void
    {
        // 1. Authenticate with Email
        $attemptEmail = Auth::attempt([
            'email' => $this->admin->email,
            'password' => 'secret123',
        ]);
        $this->assertTrue($attemptEmail);
        Auth::logout();

        // 2. Authenticate with Username
        $attemptUsername = Auth::attempt([
            'username' => $this->admin->username,
            'password' => 'secret123',
        ]);
        $this->assertTrue($attemptUsername);
        Auth::logout();

        // 3. Authenticate via Filament Custom Login Page with Username
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $this->admin->username,
                'password' => 'secret123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->admin);
        Auth::logout();

        // 4. Authenticate via Filament Custom Login Page with Email
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $this->admin->email,
                'password' => 'secret123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        $this->admin->update(['is_active' => false]);

        $this->assertFalse($this->admin->canAccessPanel(Filament::getPanel('admin')));

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $this->admin->username,
                'password' => 'secret123',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_manage_credentials_page_is_accessible_and_scoped_to_tenant(): void
    {
        $otherSchool = School::create([
            'name' => 'Other School',
            'slug' => 'other-school',
        ]);

        $otherUser = User::create([
            'name' => 'Other Admin',
            'username' => 'OTHE-9999',
            'email' => 'other@school.ma',
            'password' => Hash::make('password'),
            'school_id' => $otherSchool->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);
        Filament::setTenant($this->school);

        Livewire::test(ManageCredentials::class)
            ->assertSuccessful()
            ->assertSee($this->admin->name)
            ->assertSee($this->admin->username)
            ->assertDontSee($otherUser->username);
    }

    public function test_manage_credentials_resets_password_and_shows_modal(): void
    {
        $this->actingAs($this->admin);
        Filament::setTenant($this->school);

        Livewire::test(ManageCredentials::class)
            ->call('resetPassword', $this->admin->id)
            ->assertSet('showPasswordModal', true)
            ->assertSet('modalUsername', $this->admin->username);
    }

    public function test_printable_credential_cards_page_authorization_and_scoping(): void
    {
        // 1. Unauthenticated gets redirected
        $response = $this->get(route('filament.admin.credentials.print-cards', ['tenant' => $this->school->slug]));
        $response->assertRedirect();

        // 2. User from other school gets 403
        $otherSchool = School::create(['name' => 'Competitor', 'slug' => 'competitor']);
        $otherUser = User::create([
            'name' => 'Stranger',
            'username' => 'COMP-1111',
            'school_id' => $otherSchool->id,
            'password' => Hash::make('pass'),
        ]);

        $this->actingAs($otherUser);
        $response = $this->get(route('filament.admin.credentials.print-cards', ['tenant' => $this->school->slug]));
        $response->assertStatus(403);

        // 3. User from this school gets 200 with cards
        $this->actingAs($this->admin);
        $response = $this->get(route('filament.admin.credentials.print-cards', ['tenant' => $this->school->slug]));
        $response->assertStatus(200)
            ->assertSee($this->school->name)
            ->assertSee($this->admin->username);
    }

    public function test_student_resource_actions_generate_and_reset_credentials(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Khadija',
            'last_name' => 'Berrada',
            'phone' => '0677889900',
            'relationship_type' => 'mother',
        ]);

        $student = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $this->classroom->id,
            'first_name' => 'Sara',
            'last_name' => 'Berrada',
            'date_of_birth' => '2016-03-15',
            'gender' => 'female',
        ]);

        $this->actingAs($this->admin);
        Filament::setTenant($this->school);

        // Test generate_credentials action
        Livewire::test(ListStudents::class)
            ->callTableAction('generate_credentials', $student)
            ->assertHasNoTableActionErrors();

        $student->refresh();
        $this->assertNotNull($student->user_id);
        $this->assertNotNull($student->user->username);

        $oldPassword = $student->user->password;

        // Test reset_password action
        Livewire::test(ListStudents::class)
            ->callTableAction('reset_password', $student)
            ->assertHasNoTableActionErrors();

        $student->refresh();
        $this->assertNotEquals($oldPassword, $student->user->password);
    }
}
