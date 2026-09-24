<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\UserResource\Pages\ListUsers as TenantListUsers;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\EditUser as SuperAdminEditUser;
use App\Filament\SuperAdmin\Resources\UserResource\Pages\ListUsers as SuperAdminListUsers;
use App\Models\School;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserPasswordAndProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected School $schoolA;

    protected School $schoolB;

    protected User $adminA;

    protected User $teacherA;

    protected User $teacherB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Global Super Admin',
            'username' => 'SUPER-GLOBAL',
            'email' => 'super@platform.com',
            'password' => Hash::make('SuperSecret123!'),
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $this->schoolA = School::create([
            'name' => 'Lycée Atlas Casablanca',
            'code' => 'ATLA',
            'slug' => 'lycee-atlas',
            'is_active' => true,
        ]);

        $this->schoolB = School::create([
            'name' => 'École Oasis Rabat',
            'code' => 'OASI',
            'slug' => 'ecole-oasis',
            'is_active' => true,
        ]);

        $this->adminA = User::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Principal Atlas',
            'username' => 'ATLA-0001',
            'email' => 'principal@atlas.ma',
            'password' => Hash::make('AtlasPass123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->teacherA = User::create([
            'school_id' => $this->schoolA->id,
            'name' => 'Teacher Atlas',
            'username' => 'ATLA-1001',
            'email' => 'teacher@atlas.ma',
            'password' => Hash::make('TeacherAOldPass1!'),
            'role' => 'teacher',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->teacherB = User::create([
            'school_id' => $this->schoolB->id,
            'name' => 'Teacher Oasis',
            'username' => 'OASI-1001',
            'email' => 'teacher@oasis.ma',
            'password' => Hash::make('TeacherBOldPass1!'),
            'role' => 'teacher',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    public function test_super_admin_can_view_all_users_across_schools(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));

        Livewire::test(SuperAdminListUsers::class)
            ->assertSuccessful()
            ->assertSee($this->superAdmin->name)
            ->assertSee($this->teacherA->name)
            ->assertSee($this->teacherA->username)
            ->assertSee($this->teacherB->name)
            ->assertSee($this->teacherB->username)
            ->assertSee($this->schoolA->name)
            ->assertSee($this->schoolB->name);
    }

    public function test_super_admin_can_update_username_and_details_of_any_user(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));

        Livewire::test(SuperAdminEditUser::class, ['record' => $this->teacherA->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'username' => 'ATLA-CUSTOM-99',
                'name' => 'Teacher Atlas Renamed',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->teacherA->refresh();
        $this->assertEquals('ATLA-CUSTOM-99', $this->teacherA->username);
        $this->assertEquals('Teacher Atlas Renamed', $this->teacherA->name);
    }

    public function test_super_admin_can_reset_password_with_manual_and_random_options(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));

        // Reset with manual password
        Livewire::test(SuperAdminListUsers::class)
            ->callTableAction('reset_password', $this->teacherA, data: [
                'mode' => 'manual',
                'password' => 'NewManualPass2026!',
            ])
            ->assertHasNoTableActionErrors();

        $this->teacherA->refresh();
        $this->assertTrue(Hash::check('NewManualPass2026!', $this->teacherA->password));
        $this->assertTrue($this->teacherA->must_change_password);
        $this->assertEquals('NewManualPass2026!', $this->teacherA->temporary_password);

        // Reset with random generator for teacher B
        Livewire::test(SuperAdminListUsers::class)
            ->callTableAction('reset_password', $this->teacherB, data: [
                'mode' => 'generate',
            ])
            ->assertHasNoTableActionErrors();

        $this->teacherB->refresh();
        $this->assertNotEmpty($this->teacherB->temporary_password);
        $this->assertTrue(Hash::check($this->teacherB->temporary_password, $this->teacherB->password));
        $this->assertTrue($this->teacherB->must_change_password);
    }

    public function test_school_admin_can_only_view_users_within_their_own_tenant(): void
    {
        $this->actingAs($this->adminA);
        Filament::setTenant($this->schoolA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(TenantListUsers::class)
            ->assertSuccessful()
            ->assertSee($this->teacherA->name)
            ->assertSee($this->teacherA->username)
            ->assertDontSee($this->teacherB->name)
            ->assertDontSee($this->teacherB->username)
            ->assertDontSee($this->superAdmin->name);
    }

    public function test_school_admin_can_reset_password_for_users_in_their_tenant(): void
    {
        $this->actingAs($this->adminA);
        Filament::setTenant($this->schoolA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(TenantListUsers::class)
            ->callTableAction('reset_password', $this->teacherA, data: [
                'mode' => 'manual',
                'password' => 'TenantResetPass123!',
            ])
            ->assertHasNoTableActionErrors();

        $this->teacherA->refresh();
        $this->assertTrue(Hash::check('TenantResetPass123!', $this->teacherA->password));
        $this->assertTrue($this->teacherA->must_change_password);
        $this->assertEquals('TenantResetPass123!', $this->teacherA->temporary_password);
    }

    public function test_school_admin_cannot_reset_password_for_user_in_another_school(): void
    {
        $this->actingAs($this->adminA);
        Filament::setTenant($this->schoolA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $originalPassword = $this->teacherB->password;

        Livewire::test(TenantListUsers::class)
            ->callTableAction('reset_password', $this->teacherB, data: [
                'mode' => 'manual',
                'password' => 'HackedPass123!',
            ]);

        $this->teacherB->refresh();
        $this->assertEquals($originalPassword, $this->teacherB->password);
        $this->assertFalse(Hash::check('HackedPass123!', $this->teacherB->password));
    }

    public function test_user_can_update_their_own_password_via_profile_page(): void
    {
        $this->actingAs($this->teacherA);
        Filament::setTenant($this->schoolA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Flag teacherA with must_change_password = true
        $this->teacherA->update(['must_change_password' => true]);

        // Fail when current password is wrong
        Livewire::test(EditProfile::class)
            ->assertSuccessful()
            ->fillForm([
                'current_password' => 'WrongCurrentPassword!',
                'password' => 'BrandNewPassword2026!',
                'passwordConfirmation' => 'BrandNewPassword2026!',
            ])
            ->call('save')
            ->assertHasFormErrors(['current_password']);

        // Succeed when current password is correct
        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'Teacher Atlas Updated',
                'current_password' => 'TeacherAOldPass1!',
                'password' => 'BrandNewPassword2026!',
                'passwordConfirmation' => 'BrandNewPassword2026!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->teacherA->refresh();
        $this->assertTrue(Hash::check('BrandNewPassword2026!', $this->teacherA->password));
        $this->assertFalse($this->teacherA->must_change_password);
        $this->assertEquals('Teacher Atlas Updated', $this->teacherA->name);

        // Verify authentication succeeds with the new password
        $this->assertTrue(Auth::attempt([
            'username' => $this->teacherA->username,
            'password' => 'BrandNewPassword2026!',
        ]));
    }
}
