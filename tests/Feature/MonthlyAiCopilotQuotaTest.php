<?php

namespace Tests\Feature;

use App\Filament\Pages\AiCopilot;
use App\Filament\SuperAdmin\Resources\SchoolResource\Pages\EditSchool;
use App\Models\School;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyAiCopilotQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected School $school;

    protected User $schoolAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'SUPER-9999',
            'email' => 'super@admin.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $this->school = School::create([
            'name' => 'Atlas Academy',
            'slug' => 'atlas-academy',
            'code' => 'ATLS',
            'email' => 'contact@atlas.ma',
            'city' => 'Marrakech',
            'subscription_status' => 'active',
            'price_per_student' => 1.50,
            'monthly_minimum_charge' => 300.00,
            'is_active' => true,
        ]);

        $this->schoolAdmin = User::create([
            'name' => 'School Admin',
            'username' => 'ATLS-0001',
            'email' => 'admin@atlas.ma',
            'password' => Hash::make('password'),
            'school_id' => $this->school->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_default_quota_is_applied_to_schools(): void
    {
        $school = School::create([
            'name' => 'New School',
            'slug' => 'new-school',
            'code' => 'NEWS',
            'email' => 'info@new.ma',
            'city' => 'Rabat',
            'subscription_status' => 'active',
            'price_per_student' => 1.50,
            'monthly_minimum_charge' => 300.00,
            'is_active' => true,
        ]);

        $this->assertSame(50, $school->ai_monthly_messages_quota);
        $this->assertSame(0, $school->ai_messages_used_this_month);
        $this->assertSame(50, $school->remaining_ai_messages);
    }

    public function test_super_admin_can_set_custom_quota_via_school_resource(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('super-admin'));

        Livewire::test(EditSchool::class, ['record' => $this->school->getKey()])
            ->assertFormSet([
                'ai_monthly_messages_quota' => 50,
            ])
            ->fillForm([
                'ai_monthly_messages_quota' => 150,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->school->refresh();
        $this->assertSame(150, $this->school->ai_monthly_messages_quota);
        $this->assertSame(150, $this->school->remaining_ai_messages);
    }

    public function test_ai_copilot_view_displays_counter_and_enforces_quota(): void
    {
        $this->school->update([
            'ai_monthly_messages_quota' => 60,
            'ai_messages_used_this_month' => 15,
        ]);

        $this->actingAs($this->schoolAdmin);
        Filament::setTenant($this->school);

        // 1. Assert counter displays 45 / 60 remaining
        Livewire::test(AiCopilot::class)
            ->assertSee('الرصيد المتبقي: 45 / 60 رسالة لهذا الشهر')
            ->assertDontSee('تم استنفاد كوطة رسائل المساعد الذكي لهذا الشهر');

        // 2. Send a message and assert usage increments
        Livewire::test(AiCopilot::class)
            ->set('prompt', 'Hello, how can you help me today?')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->school->refresh();
        $this->assertSame(16, $this->school->ai_messages_used_this_month);
        $this->assertSame(44, $this->school->remaining_ai_messages);

        // 3. Exhaust quota completely
        $this->school->update([
            'ai_messages_used_this_month' => 60,
        ]);

        Livewire::test(AiCopilot::class)
            ->assertSee('الرصيد المتبقي: 0 / 60 رسالة لهذا الشهر')
            ->assertSee('تم استنفاد كوطة رسائل المساعد الذكي لهذا الشهر. سيتجدد الرصيد تلقائياً بداية الشهر القادم.')
            ->set('prompt', 'Can I still send a prompt?')
            ->call('sendMessage');

        // Usage must NOT exceed 60
        $this->school->refresh();
        $this->assertSame(60, $this->school->ai_messages_used_this_month);
    }

    public function test_bill_schools_monthly_resets_usage_while_preserving_custom_quotas(): void
    {
        // School 1 has default quota 50, used 45
        $this->school->update([
            'ai_monthly_messages_quota' => 50,
            'ai_messages_used_this_month' => 45,
        ]);

        // School 2 has custom quota 200, used 180
        $school2 = School::create([
            'name' => 'High Quota School',
            'slug' => 'high-quota',
            'code' => 'HQSC',
            'email' => 'hq@school.ma',
            'city' => 'Casablanca',
            'subscription_status' => 'active',
            'price_per_student' => 1.50,
            'monthly_minimum_charge' => 300.00,
            'is_active' => true,
            'ai_monthly_messages_quota' => 200,
            'ai_messages_used_this_month' => 180,
        ]);

        // Execute billing command for active academic month (September = 9)
        $this->artisan('app:bill-schools-monthly', ['--month' => 9, '--year' => 2026])
            ->assertSuccessful();

        $this->school->refresh();
        $school2->refresh();

        // Usage is reset to 0
        $this->assertSame(0, $this->school->ai_messages_used_this_month);
        $this->assertSame(0, $school2->ai_messages_used_this_month);

        // Custom quotas are preserved
        $this->assertSame(50, $this->school->ai_monthly_messages_quota);
        $this->assertSame(200, $school2->ai_monthly_messages_quota);

        // Verify that summer break (July/August) skips reset
        $this->school->update(['ai_messages_used_this_month' => 25]);
        $this->artisan('app:bill-schools-monthly', ['--month' => 7, '--year' => 2026])
            ->assertSuccessful();

        $this->school->refresh();
        $this->assertSame(25, $this->school->ai_messages_used_this_month);
    }
}
