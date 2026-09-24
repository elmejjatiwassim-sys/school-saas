<?php

namespace Tests\Feature;

use App\Enums\AiActionLogStatus;
use App\Enums\AttendanceStatus;
use App\Filament\Pages\AiCopilot;
use App\Models\AcademicYear;
use App\Models\AiActionLog;
use App\Models\AiConversation;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\AiCopilotService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiCopilotConfirmationAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_prompt_creates_pending_audit_log_without_direct_execution(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'name' => 'CP-A']);

        $service = app(AiCopilotService::class);
        $result = $service->processPrompt('Register student Yassine Chraibi in CP-A phone 0612345678', $school, $user);

        $this->assertEquals('proposal', $result['type']);
        $this->assertNotNull($result['action_log']);

        $log = $result['action_log'];
        $this->assertEquals($school->id, $log->school_id);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals('register_student', $log->action_type);
        $this->assertEquals(AiActionLogStatus::PendingConfirmation, $log->status);
        $this->assertNull($log->confirmed_at);
        $this->assertEquals('Yassine', $log->payload['first_name']);
        $this->assertEquals('Chraibi', $log->payload['last_name']);

        // Assert database mutation did NOT happen yet
        $this->assertDatabaseMissing('students', [
            'first_name' => 'Yassine',
            'last_name' => 'Chraibi',
        ]);
    }

    public function test_confirming_action_executes_service_and_updates_audit_log(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'CP-A']);

        $service = app(AiCopilotService::class);
        $result = $service->processPrompt('Register student Yassine Chraibi in CP-A phone 0612345678', $school, $user);
        $log = $result['action_log'];

        // Confirm execution
        $execution = $service->executeAction($log, $user);

        $this->assertTrue($execution['success']);
        $this->assertStringContainsString('Yassine Chraibi', $execution['message']);

        // Assert student was created
        $this->assertDatabaseHas('students', [
            'school_id' => $school->id,
            'first_name' => 'Yassine',
            'last_name' => 'Chraibi',
        ]);

        // Assert log status updated to executed with timestamp
        $log->refresh();
        $this->assertEquals(AiActionLogStatus::Executed, $log->status);
        $this->assertNotNull($log->confirmed_at);
        $this->assertEquals($user->id, $log->user_id);
    }

    public function test_cancelling_action_marks_audit_log_as_cancelled_without_database_mutations(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'name' => 'CP-A']);

        $service = app(AiCopilotService::class);
        $result = $service->processPrompt('Register student Sami Bennani in CP-A phone 0612345678', $school, $user);
        $log = $result['action_log'];

        // Cancel execution
        $cancelledLog = $service->cancelAction($log, $user);

        $this->assertEquals(AiActionLogStatus::Cancelled, $cancelledLog->status);
        $this->assertNull($cancelledLog->confirmed_at);

        // Ensure no student was created
        $this->assertDatabaseMissing('students', [
            'first_name' => 'Sami',
            'last_name' => 'Bennani',
        ]);
    }

    public function test_ai_copilot_quick_attendance_confirmation_flow(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'first_name' => 'Omar',
            'last_name' => 'Berrada',
        ]);

        $service = app(AiCopilotService::class);
        $result = $service->processPrompt('Mark Omar Berrada absent today', $school, $user);

        $this->assertEquals('proposal', $result['type']);
        $log = $result['action_log'];
        $this->assertEquals('quick_attendance', $log->action_type);
        $this->assertEquals('absent', $log->payload['status']);

        // Before confirmation: no attendance record
        $this->assertDatabaseMissing('attendance_records', [
            'student_id' => $student->id,
            'status' => AttendanceStatus::Absent->value,
        ]);

        // Confirm
        $service->executeAction($log, $user);

        // After confirmation: attendance record created
        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $school->id,
            'student_id' => $student->id,
            'status' => AttendanceStatus::Absent->value,
        ]);
    }

    public function test_ai_copilot_generate_invoices_confirmation_flow(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'CM1']);
        $student = Student::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'status' => 'active',
        ]);

        $service = app(AiCopilotService::class);
        $result = $service->processPrompt('Generate invoices for classroom CM1', $school, $user);

        $log = $result['action_log'];
        $this->assertEquals('generate_invoices', $log->action_type);

        $this->assertEquals(0, Invoice::where('student_id', $student->id)->count());

        // Confirm action
        $service->executeAction($log, $user);

        $this->assertEquals(10, Invoice::where('student_id', $student->id)->count());
    }

    public function test_annual_archive_command_archives_active_conversations(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        $active1 = AiConversation::create(['school_id' => $school->id, 'user_id' => $user->id, 'is_archived' => false]);
        $active2 = AiConversation::create(['school_id' => $school->id, 'user_id' => $user->id, 'is_archived' => false]);
        $alreadyArchived = AiConversation::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'is_archived' => true,
            'archived_at' => now()->subMonths(6),
        ]);

        $this->artisan('ai:archive-conversations')
            ->expectsOutput('Starting annual archive of AI Copilot conversations...')
            ->expectsOutput('Successfully archived 2 active AI conversations.')
            ->assertExitCode(0);

        $this->assertTrue($active1->fresh()->is_archived);
        $this->assertNotNull($active1->fresh()->archived_at);
        $this->assertTrue($active2->fresh()->is_archived);
        $this->assertNotNull($active2->fresh()->archived_at);
        $this->assertTrue($alreadyArchived->fresh()->is_archived);
    }

    public function test_ai_copilot_livewire_page_interaction_and_confirmation(): void
    {
        $school = School::factory()->create(['slug' => 'future-school']);
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id, 'name' => 'CP-A']);

        $this->actingAs($user);
        Filament::setTenant($school);

        $livewire = Livewire::test(AiCopilot::class)
            ->assertSuccessful()
            ->set('prompt', 'Register student Nadia Idrissi in CP-A phone 0699887766')
            ->call('sendMessage');

        $log = AiActionLog::where('school_id', $school->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(AiActionLogStatus::PendingConfirmation, $log->status);

        // Call confirmAction from Livewire
        $livewire->call('confirmAction', $log->id)
            ->assertHasNoErrors();

        $this->assertEquals(AiActionLogStatus::Executed, $log->fresh()->status);
        $this->assertDatabaseHas('students', [
            'first_name' => 'Nadia',
            'last_name' => 'Idrissi',
        ]);
    }

    public function test_ai_action_logs_resource_page_accessible(): void
    {
        $school = School::factory()->create(['slug' => 'future-school']);
        $user = User::factory()->create(['school_id' => $school->id]);

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/ai-action-logs")
            ->assertSuccessful();

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/ai-copilot")
            ->assertSuccessful();
    }
}
