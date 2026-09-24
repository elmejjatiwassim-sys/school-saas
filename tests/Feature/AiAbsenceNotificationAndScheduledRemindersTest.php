<?php

namespace Tests\Feature;

use App\Enums\AiActionLogStatus;
use App\Enums\AttendanceSession;
use App\Enums\AttendanceStatus;
use App\Enums\InvoiceStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AbsenceAlertNotification;
use App\Notifications\InvoiceReminderNotification;
use App\Services\AdminAiAssistantService;
use App\Services\AiCopilotService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AiAbsenceNotificationAndScheduledRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected User $admin;

    protected Classroom $classroomA;

    protected Classroom $classroomB;

    protected AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Al-Hikma Academy',
            'slug' => 'al-hikma',
            'email' => 'admin@alhikma.ma',
            'phone' => '0522334455',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Director',
            'username' => 'HIKM-1001',
            'email' => 'director@alhikma.ma',
            'password' => bcrypt('password'),
            'school_id' => $this->school->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);

        $gradeLevel = GradeLevel::create([
            'school_id' => $this->school->id,
            'name' => 'Level 1',
            'code' => 'L1',
        ]);

        $this->classroomA = Classroom::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'CE1-A',
        ]);

        $this->classroomB = Classroom::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'CE1-B',
        ]);
    }

    public function test_ai_copilot_tool_notify_absent_students_creates_pending_card_with_absent_details(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Hamza',
            'last_name' => 'Tazi',
            'phone' => '0661112233',
            'email' => 'hamza@tazi.ma',
            'relationship_type' => 'father',
        ]);

        $student = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $this->classroomA->id,
            'first_name' => 'Adam',
            'last_name' => 'Tazi',
            'gender' => 'male',
        ]);

        $today = now()->toDateString();

        AttendanceRecord::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'classroom_id' => $this->classroomA->id,
            'academic_year_id' => $this->academicYear->id,
            'date' => $today,
            'session' => AttendanceSession::Morning,
            'status' => AttendanceStatus::Absent,
        ]);

        Notification::fake();

        $service = app(AiCopilotService::class);
        $result = $service->processPrompt("Notify guardians of absent students today {$today}", $this->school, $this->admin);

        $this->assertEquals('proposal', $result['type']);
        $this->assertNotNull($result['action_log']);

        $log = $result['action_log'];
        $this->assertEquals('notify_absent_students_guardians', $log->action_type);
        $this->assertEquals(AiActionLogStatus::PendingConfirmation, $log->status);
        $this->assertEquals(1, $log->payload['absent_students_count']);
        $this->assertStringContainsString('Adam Tazi', $log->payload['absent_students']);
        $this->assertEquals($today, $log->payload['date']);

        // Assert no notification sent prior to user confirmation
        Notification::assertNothingSent();
    }

    public function test_confirming_ai_absence_action_dispatches_notifications_and_marks_executed(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Laila',
            'last_name' => 'Idrissi',
            'phone' => '0662223344',
            'email' => 'laila@idrissi.ma',
            'relationship_type' => 'mother',
        ]);

        $student = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $this->classroomA->id,
            'first_name' => 'Mehdi',
            'last_name' => 'Idrissi',
            'gender' => 'male',
        ]);

        $today = now()->toDateString();

        $attendance = AttendanceRecord::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'classroom_id' => $this->classroomA->id,
            'academic_year_id' => $this->academicYear->id,
            'date' => $today,
            'session' => AttendanceSession::Morning,
            'status' => AttendanceStatus::Absent,
        ]);

        Notification::fake();

        $service = app(AdminAiAssistantService::class);
        $result = $service->processPrompt("بلغ أولياء التلاميذ الغائبين اليوم {$today}", $this->school, $this->admin);
        $log = $result['action_log'];

        $execution = $service->executeAction($log, $this->admin);

        $this->assertTrue($execution['success']);
        $this->assertStringContainsString('Successfully dispatched absence notifications', $execution['message']);

        // Assert notification dispatched to guardian
        Notification::assertSentTo(
            $guardian,
            AbsenceAlertNotification::class,
            function (AbsenceAlertNotification $notification) use ($student) {
                return $notification->student->id === $student->id;
            }
        );

        $log->refresh();
        $this->assertEquals(AiActionLogStatus::Executed, $log->status);
        $this->assertNotNull($log->confirmed_at);
    }

    public function test_absence_notification_scoped_strictly_to_tenant(): void
    {
        $otherSchool = School::create(['name' => 'Other School', 'slug' => 'other-school']);
        $otherGuardian = Guardian::create([
            'school_id' => $otherSchool->id,
            'first_name' => 'Other',
            'last_name' => 'Parent',
            'phone' => '0699999999',
            'relationship_type' => 'father',
        ]);
        $otherAcademicYear = AcademicYear::create([
            'school_id' => $otherSchool->id,
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ]);
        $otherGradeLevel = GradeLevel::create([
            'school_id' => $otherSchool->id,
            'name' => 'Other Level',
            'code' => 'OL',
        ]);
        $otherClassroom = Classroom::create([
            'school_id' => $otherSchool->id,
            'academic_year_id' => $otherAcademicYear->id,
            'grade_level_id' => $otherGradeLevel->id,
            'name' => 'Other Class',
        ]);
        $otherStudent = Student::create([
            'school_id' => $otherSchool->id,
            'guardian_id' => $otherGuardian->id,
            'classroom_id' => $otherClassroom->id,
            'first_name' => 'Other',
            'last_name' => 'Student',
            'gender' => 'male',
        ]);

        $today = now()->toDateString();
        AttendanceRecord::create([
            'school_id' => $otherSchool->id,
            'student_id' => $otherStudent->id,
            'classroom_id' => $otherClassroom->id,
            'academic_year_id' => $this->academicYear->id,
            'date' => $today,
            'session' => AttendanceSession::Morning,
            'status' => AttendanceStatus::Absent,
        ]);

        Notification::fake();

        $service = app(AiCopilotService::class);
        $result = $service->processPrompt("Notify guardians of absent students today {$today}", $this->school, $this->admin);

        // Current school has no absentees
        $log = $result['action_log'];
        $this->assertEquals(0, $log->payload['absent_students_count']);

        $service->executeAction($log, $this->admin);

        Notification::assertNothingSent();
    }

    public function test_send_monthly_invoice_reminders_command_skips_july_and_august(): void
    {
        Notification::fake();

        // July (Month 7)
        $this->artisan('app:send-monthly-invoice-reminders', ['--month' => 7, '--type' => 'initial'])
            ->expectsOutputToContain('summer break')
            ->assertSuccessful();

        // August (Month 8)
        $this->artisan('app:send-monthly-invoice-reminders', ['--month' => 8, '--type' => 'reminder'])
            ->expectsOutputToContain('summer break')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_send_monthly_invoice_reminders_initial_type_on_first_of_month(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Youssef',
            'last_name' => 'Mansouri',
            'phone' => '0611447788',
            'relationship_type' => 'father',
        ]);

        $student = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $this->classroomA->id,
            'first_name' => 'Nour',
            'last_name' => 'Mansouri',
            'gender' => 'female',
        ]);

        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'invoice_number' => 'INV-2026-10-001',
            'billing_month' => 10,
            'billing_year' => 2026,
            'title' => 'واجب شهر أكتوبر 2026',
            'total_amount' => 1500.00,
            'paid_amount' => 0.00,
            'status' => InvoiceStatus::Unpaid,
            'due_date' => '2026-10-10',
        ]);

        Notification::fake();

        $this->artisan('app:send-monthly-invoice-reminders', [
            '--type' => 'initial',
            '--month' => 10,
            '--year' => 2026,
        ])
            ->expectsOutputToContain('Successfully dispatched 1 initial invoice reminders.')
            ->assertSuccessful();

        Notification::assertSentTo(
            $guardian,
            InvoiceReminderNotification::class,
            function (InvoiceReminderNotification $notification) use ($student) {
                return str_contains($notification->reminderMessage, 'نحيطكم علماً بأن واجب التمدرس لشهر أكتوبر')
                    && str_contains($notification->reminderMessage, $student->fullName)
                    && str_contains($notification->reminderMessage, 'متاح للأداء')
                    && $notification->type === 'initial';
            }
        );
    }

    public function test_send_monthly_invoice_reminders_reminder_type_on_fifth_of_month(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Salma',
            'last_name' => 'Fassi',
            'phone' => '0622558899',
            'relationship_type' => 'mother',
        ]);

        $student = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $this->classroomA->id,
            'first_name' => 'Rayane',
            'last_name' => 'Fassi',
            'gender' => 'male',
        ]);

        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'invoice_number' => 'INV-2026-10-002',
            'billing_month' => 10,
            'billing_year' => 2026,
            'title' => 'واجب شهر أكتوبر 2026',
            'total_amount' => 1800.00,
            'paid_amount' => 0.00,
            'status' => InvoiceStatus::Unpaid,
            'due_date' => '2026-10-10',
        ]);

        Notification::fake();

        $this->artisan('app:send-monthly-invoice-reminders', [
            '--type' => 'reminder',
            '--month' => 10,
            '--year' => 2026,
        ])
            ->expectsOutputToContain('Successfully dispatched 1 reminder invoice reminders.')
            ->assertSuccessful();

        Notification::assertSentTo(
            $guardian,
            InvoiceReminderNotification::class,
            function (InvoiceReminderNotification $notification) use ($student) {
                return str_contains($notification->reminderMessage, 'تذكير ودي: نرجو تسوية واجبات التمدرس لشهر أكتوبر')
                    && str_contains($notification->reminderMessage, $student->fullName)
                    && $notification->type === 'reminder';
            }
        );
    }

    public function test_paid_invoices_are_not_reminded(): void
    {
        $guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Ali',
            'last_name' => 'Bennani',
            'phone' => '0633669900',
            'relationship_type' => 'father',
        ]);

        $student = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $this->classroomA->id,
            'first_name' => 'Zayd',
            'last_name' => 'Bennani',
            'gender' => 'male',
        ]);

        Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'invoice_number' => 'INV-2026-10-003',
            'billing_month' => 10,
            'billing_year' => 2026,
            'title' => 'واجب شهر أكتوبر 2026',
            'total_amount' => 1500.00,
            'paid_amount' => 1500.00,
            'status' => InvoiceStatus::Paid,
            'due_date' => '2026-10-10',
        ]);

        Notification::fake();

        $this->artisan('app:send-monthly-invoice-reminders', [
            '--type' => 'reminder',
            '--month' => 10,
            '--year' => 2026,
        ])
            ->expectsOutputToContain('No unpaid or pending invoices found')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_routes_console_registers_schedules(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $initialEvent = $events->first(fn ($e) => str_contains($e->command ?? '', 'app:send-monthly-invoice-reminders --type=initial'));
        $reminderEvent = $events->first(fn ($e) => str_contains($e->command ?? '', 'app:send-monthly-invoice-reminders --type=reminder'));

        $this->assertNotNull($initialEvent, 'Schedule for --type=initial not found.');
        $this->assertNotNull($reminderEvent, 'Schedule for --type=reminder not found.');

        // Verify cron expression: 0 9 1 * * (1st of month at 09:00)
        $this->assertEquals('0 9 1 * *', $initialEvent->expression);

        // Verify cron expression: 0 10 5 * * (5th of month at 10:00)
        $this->assertEquals('0 10 5 * *', $reminderEvent->expression);
    }
}
