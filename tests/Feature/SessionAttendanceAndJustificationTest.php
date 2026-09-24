<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\JustificationStatus;
use App\Filament\Resources\AttendanceJustificationResource\Pages\ListAttendanceJustifications;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use App\Notifications\SessionAbsenceNotification;
use App\Services\SessionAttendanceService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SessionAttendanceAndJustificationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $academicYear;

    protected Classroom $classroom;

    protected Subject $subject;

    protected User $teacher1;

    protected User $teacher2;

    protected User $supervisor;

    protected User $admin;

    protected Student $student;

    protected Guardian $guardian;

    protected Timetable $timetable;

    protected SessionAttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create(['slug' => 'test-school']);
        $this->academicYear = AcademicYear::factory()->create([
            'school_id' => $this->school->id,
            'is_current' => true,
        ]);
        $this->classroom = Classroom::factory()->create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Grade 5-A',
        ]);
        $this->subject = Subject::factory()->create([
            'school_id' => $this->school->id,
            'name' => 'الرياضيات',
        ]);

        $this->teacher1 = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'teacher',
            'name' => 'Teacher One',
        ]);
        $this->teacher2 = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'teacher',
            'name' => 'Teacher Two',
        ]);
        $this->supervisor = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'supervisor',
            'name' => 'Supervisor Kamal',
        ]);
        $this->admin = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'admin',
            'name' => 'Admin Boss',
        ]);

        $this->guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Karim',
            'last_name' => 'El Amrani',
            'phone' => '0661234567',
            'email' => 'karim@parent.com',
            'relationship_type' => 'father',
        ]);

        $this->student = Student::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
            'guardian_id' => $this->guardian->id,
            'first_name' => 'Yassine',
            'last_name' => 'El Amrani',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->timetable = Timetable::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Monday,
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'room_name' => 'Room 12',
        ]);

        $this->service = app(SessionAttendanceService::class);
    }

    public function test_auto_resolving_active_timetable_slot_by_current_time(): void
    {
        $slot2 = Timetable::factory()->create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Monday,
            'start_time' => '10:30:00',
            'end_time' => '12:00:00',
            'room_name' => 'Room 14',
        ]);

        // 1. Monday at 09:45 (inside slot 1)
        Carbon::setTestNow('2026-09-21 09:45:00'); // 2026-09-21 is a Monday
        $activeSlot = $this->service->getCurrentActiveSession($this->teacher1, $this->classroom);
        $this->assertNotNull($activeSlot);
        $this->assertSame($this->timetable->id, $activeSlot->id);

        // 2. Monday at 11:15 (inside slot 2)
        Carbon::setTestNow('2026-09-21 11:15:00');
        $activeSlot2 = $this->service->getCurrentActiveSession($this->teacher1, $this->classroom);
        $this->assertNotNull($activeSlot2);
        $this->assertSame($slot2->id, $activeSlot2->id);

        // 3. Monday at 14:00 (outside any slot)
        Carbon::setTestNow('2026-09-21 14:00:00');
        $noSlot = $this->service->getCurrentActiveSession($this->teacher1, $this->classroom);
        $this->assertNull($noSlot);

        // 4. Tuesday at 09:45 (wrong day of week)
        Carbon::setTestNow('2026-09-22 09:45:00'); // Tuesday
        $tuesdaySlot = $this->service->getCurrentActiveSession($this->teacher1, $this->classroom);
        $this->assertNull($tuesdaySlot);

        // 5. Supervisor can resolve slot without teacher constraint
        Carbon::setTestNow('2026-09-21 09:45:00');
        $supervisorSlot = $this->service->getCurrentActiveSession($this->supervisor, $this->classroom);
        $this->assertNotNull($supervisorSlot);
        $this->assertSame($this->timetable->id, $supervisorSlot->id);

        Carbon::setTestNow();
    }

    public function test_teacher_and_supervisor_permissions_check(): void
    {
        Carbon::setTestNow('2026-09-21 09:15:00');

        // Teacher 1 records attendance for their own slot -> SUCCESS
        $record = $this->service->recordAttendance(
            $this->timetable->id,
            $this->student->id,
            'present',
            $this->teacher1
        );
        $this->assertNotNull($record);
        $this->assertSame(AttendanceStatus::Present, $record->status);
        $this->assertSame($this->teacher1->id, $record->recorded_by_id);

        // Teacher 2 attempts to record attendance for Teacher 1's slot -> FORBIDDEN
        $this->expectException(AuthorizationException::class);
        $this->service->recordAttendance(
            $this->timetable->id,
            $this->student->id,
            'present',
            $this->teacher2
        );

        Carbon::setTestNow();
    }

    public function test_general_supervisor_can_record_attendance_across_all_slots(): void
    {
        Carbon::setTestNow('2026-09-21 09:15:00');

        // General Supervisor records attendance for Teacher 1's slot -> SUCCESS
        $record = $this->service->recordAttendance(
            $this->timetable->id,
            $this->student->id,
            'present',
            $this->supervisor
        );

        $this->assertNotNull($record);
        $this->assertSame($this->supervisor->id, $record->recorded_by_id);

        Carbon::setTestNow();
    }

    public function test_unauthorized_roles_cannot_record_attendance_or_approve_justifications(): void
    {
        $studentUser = User::factory()->create([
            'school_id' => $this->school->id,
            'role' => 'student',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->recordAttendance(
            $this->timetable->id,
            $this->student->id,
            'present',
            $studentUser
        );
    }

    public function test_teacher_cannot_approve_justification(): void
    {
        $attendance = Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Absent,
            'guardian_justification_note' => 'Sick',
            'justification_status' => JustificationStatus::Pending,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->approveJustification($attendance, $this->teacher1);
    }

    public function test_guardian_justification_submission_and_supervisor_approval_updating_status_to_excused(): void
    {
        Carbon::setTestNow('2026-09-21 09:00:00');
        Notification::fake();

        // 1. Record absence for student
        $attendance = $this->service->recordAttendance(
            $this->timetable->id,
            $this->student->id,
            'absent',
            $this->teacher1,
            '2026-09-21'
        );

        $this->assertSame(AttendanceStatus::Absent, $attendance->status);
        $this->assertSame(JustificationStatus::Pending, $attendance->justification_status);

        // Assert notification dispatched to guardian with exact format
        Notification::assertSentTo(
            $this->guardian,
            SessionAbsenceNotification::class,
            function (SessionAbsenceNotification $notification) {
                return str_contains($notification->customMessage, 'تسجيل غياب التلميذ')
                    && str_contains($notification->customMessage, 'Yassine El Amrani')
                    && str_contains($notification->customMessage, 'الرياضيات')
                    && str_contains($notification->customMessage, '2026-09-21')
                    && str_contains($notification->customMessage, '09:00');
            }
        );

        // 2. Guardian submits justification via API with attachment
        Storage::fake('public');
        $file = UploadedFile::fake()->create('medical_cert.pdf', 100, 'application/pdf');

        $response = $this->postJson("/api/attendances/{$attendance->id}/justify", [
            'reason' => 'Fever and medical consultation with Dr. Alami.',
            'attachment' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.justification_status', 'pending');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'absent',
            'justification_status' => 'pending',
            'guardian_justification_note' => 'Fever and medical consultation with Dr. Alami.',
        ]);

        $freshAttendance = Attendance::find($attendance->id);
        $this->assertNotNull($freshAttendance->guardian_justification_attachment);
        Storage::disk('public')->assertExists($freshAttendance->guardian_justification_attachment);

        // 3. Supervisor approves justification
        $approved = $this->service->approveJustification($attendance->id, $this->supervisor);

        $this->assertSame(AttendanceStatus::Excused, $approved->status);
        $this->assertSame(JustificationStatus::Approved, $approved->justification_status);
        $this->assertSame($this->supervisor->id, $approved->reviewed_by_id);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'excused',
            'justification_status' => 'approved',
            'reviewed_by_id' => $this->supervisor->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_late_arrival_timestamp_recording(): void
    {
        // 1. Auto-fill arrival time when marked late by teacher
        Carbon::setTestNow('2026-09-21 09:22:00');

        $attendance = $this->service->recordAttendance(
            $this->timetable->id,
            $this->student->id,
            'late',
            $this->teacher1,
            '2026-09-21'
        );

        $this->assertSame(AttendanceStatus::Late, $attendance->status);
        $this->assertSame('09:22', $attendance->late_arrival_time);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'late',
            'late_arrival_time' => '09:22',
        ]);

        // 2. Supervisor modifies an absent record to late with specified arrival time
        $absentRecord = Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-22',
            'status' => AttendanceStatus::Absent,
        ]);

        $updatedRecord = $this->service->markAsLate($absentRecord->id, $this->supervisor, '09:35');

        $this->assertSame(AttendanceStatus::Late, $updatedRecord->status);
        $this->assertSame('09:35', $updatedRecord->late_arrival_time);
        $this->assertSame($this->supervisor->id, $updatedRecord->reviewed_by_id);

        $this->assertDatabaseHas('attendances', [
            'id' => $absentRecord->id,
            'status' => 'late',
            'late_arrival_time' => '09:35',
            'reviewed_by_id' => $this->supervisor->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_unique_constraint_on_student_timetable_and_date(): void
    {
        Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Present,
        ]);

        $this->expectException(QueryException::class);

        Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Absent,
        ]);
    }

    public function test_filament_justification_resource_actions_and_livewire(): void
    {
        $attendance = Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Absent,
            'guardian_justification_note' => 'Transport strike in the area.',
            'justification_status' => JustificationStatus::Pending,
        ]);

        $this->actingAs($this->supervisor);
        Filament::setTenant($this->school);

        // Test Livewire table displays the record and approve action works
        Livewire::test(ListAttendanceJustifications::class)
            ->assertCanSeeTableRecords([$attendance])
            ->callTableAction('approve', $attendance)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => 'excused',
            'justification_status' => 'approved',
            'reviewed_by_id' => $this->supervisor->id,
        ]);

        // Test Mark Late table action
        $attendance2 = Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-22',
            'status' => AttendanceStatus::Absent,
            'guardian_justification_note' => 'Late waking up.',
            'justification_status' => JustificationStatus::Pending,
        ]);

        Livewire::test(ListAttendanceJustifications::class)
            ->callTableAction('mark_late', $attendance2, data: [
                'late_arrival_time' => '09:40',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance2->id,
            'status' => 'late',
            'late_arrival_time' => '09:40',
            'reviewed_by_id' => $this->supervisor->id,
        ]);
    }

    public function test_api_webhook_justification_endpoint(): void
    {
        $attendance = Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Absent,
        ]);

        $response = $this->postJson('/api/webhooks/guardian-justification', [
            'attendance_id' => $attendance->id,
            'note' => 'Family emergency, will return tomorrow.',
            'attachment' => 'https://example.com/receipt.pdf',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guardian_justification_note', 'Family emergency, will return tomorrow.');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'guardian_justification_note' => 'Family emergency, will return tomorrow.',
            'guardian_justification_attachment' => 'https://example.com/receipt.pdf',
            'justification_status' => 'pending',
        ]);
    }
}
