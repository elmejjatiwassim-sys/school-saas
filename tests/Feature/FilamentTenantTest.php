<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }
    public function test_admin_login_page_is_accessible(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_is_redirected_to_tenant_dashboard(): void
    {
        $school = School::where('slug', 'al-amal')->firstOrFail();
        $user = User::where('email', 'admin@al-amal.school')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect('/admin/al-amal');
    }

    public function test_tenant_dashboard_is_accessible_by_school_user(): void
    {
        $school = School::where('slug', 'al-amal')->firstOrFail();
        $user = User::where('email', 'admin@al-amal.school')->firstOrFail();

        $response = $this->actingAs($user)->get('/admin/al-amal');

        $response->assertStatus(200);
        $response->assertSee('Al Amal School');
    }
}
