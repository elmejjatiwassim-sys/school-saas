<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\JustificationStatus;
use App\Enums\PaymentMethod;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\FeeType;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use App\Notifications\SessionAbsenceNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $academicYear;

    protected Classroom $classroom;

    protected Subject $subject;

    protected User $teacher;

    protected User $supervisor;

    protected Guardian $guardian;

    protected User $guardianUser;

    protected Student $student1;

    protected Student $student2;

    protected Timetable $timetable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Elite International Academy',
            'slug' => 'elite-academy',
            'code' => 'ELIT',
            'email' => 'contact@elite.ma',
            'phone' => '+212522001122',
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
            'name' => 'Grade 4',
            'code' => 'G4',
        ]);

        $this->classroom = Classroom::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'Class 4-B',
            'capacity' => 30,
        ]);

        $this->subject = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'Science & Physics',
        ]);

        $this->teacher = User::create([
            'school_id' => $this->school->id,
            'name' => 'Professor Tariq',
            'email' => 'tariq@elite.ma',
            'phone' => '+212600112233',
            'username' => 'ELIT-1010',
            'password' => Hash::make('Teacher@2026'),
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->supervisor = User::create([
            'school_id' => $this->school->id,
            'name' => 'Supervisor Nabil',
            'email' => 'nabil@elite.ma',
            'phone' => '+212600998877',
            'password' => Hash::make('Super@2026'),
            'role' => 'supervisor',
            'is_active' => true,
        ]);

        $this->guardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Rachid',
            'last_name' => 'Alaoui',
            'phone' => '+212611445566',
            'email' => 'rachid.alaoui@example.com',
            'relationship_type' => 'father',
        ]);

        $this->guardianUser = User::create([
            'school_id' => $this->school->id,
            'guardian_id' => $this->guardian->id,
            'name' => 'Rachid Alaoui',
            'email' => 'rachid.alaoui@example.com',
            'phone' => '+212611445566',
            'password' => Hash::make('Guardian@2026'),
            'role' => 'guardian',
            'is_active' => true,
        ]);

        $this->student1 = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $this->guardian->id,
            'classroom_id' => $this->classroom->id,
            'first_name' => 'Adam',
            'last_name' => 'Alaoui',
            'gender' => Gender::Male,
            'registration_number' => 'REG-2026-0010',
            'massar_code' => 'K131000001',
            'monthly_tuition_fee' => 1800.00,
        ]);

        $this->student2 = Student::create([
            'school_id' => $this->school->id,
            'guardian_id' => $this->guardian->id,
            'classroom_id' => $this->classroom->id,
            'first_name' => 'Sara',
            'last_name' => 'Alaoui',
            'gender' => Gender::Female,
            'registration_number' => 'REG-2026-0011',
            'massar_code' => 'K131000002',
            'monthly_tuition_fee' => 1800.00,
        ]);

        $this->timetable = Timetable::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'classroom_id' => $this->classroom->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);
    }

    public function test_teacher_login_and_token_generation_via_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'tariq@elite.ma',
            'password' => 'Teacher@2026',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonPath('user.school.id', $this->school->id);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_supports_phone_number_identifier(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+212611445566',
            'password' => 'Guardian@2026',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'guardian')
            ->assertJsonPath('user.email', 'rachid.alaoui@example.com');
    }

    public function test_login_rejected_when_school_tenant_is_inactive(): void
    {
        $this->school->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'tariq@elite.ma',
            'password' => 'Teacher@2026',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_logout_revokes_bearer_token(): void
    {
        $token = $this->teacher->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_auth_me_returns_profile_and_school_tenant(): void
    {
        $token = $this->teacher->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $this->teacher->id)
            ->assertJsonPath('user.school.slug', 'elite-academy');
    }

    public function test_teacher_active_session_resolution_and_student_list(): void
    {
        $token = $this->teacher->createToken('test')->plainTextToken;

        // When time does not match active session
        Carbon::setTestNow('2026-09-21 14:00:00'); // Monday afternoon (no class)
        $noSessionResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/teacher/active-session');

        $noSessionResponse->assertOk()
            ->assertJsonPath('has_active_session', false);

        // When time matches active session slot
        Carbon::setTestNow('2026-09-21 09:15:00'); // Monday 09:15 during Science class
        $activeSessionResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/teacher/active-session');

        $activeSessionResponse->assertOk()
            ->assertJsonPath('has_active_session', true)
            ->assertJsonPath('session.timetable_id', $this->timetable->id)
            ->assertJsonPath('session.subject_name', 'Science & Physics')
            ->assertJsonPath('session.classroom_name', 'Class 4-B');

        $students = $activeSessionResponse->json('students');
        $this->assertCount(2, $students);
        $this->assertEquals($this->student1->id, $students[0]['id']);

        Carbon::setTestNow();
    }

    public function test_teacher_bulk_attendance_recording_and_absence_dispatching(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-09-21 09:25:00');

        $token = $this->teacher->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/teacher/attendances/bulk-record', [
                'timetable_id' => $this->timetable->id,
                'date' => '2026-09-21',
                'records' => [
                    [
                        'student_id' => $this->student1->id,
                        'status' => 'absent',
                    ],
                    [
                        'student_id' => $this->student2->id,
                        'status' => 'late',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('recorded_count', 2);

        // Verify absent record created
        $att1 = Attendance::where('student_id', $this->student1->id)
            ->where('timetable_id', $this->timetable->id)
            ->first();
        $this->assertNotNull($att1);
        $this->assertEquals('absent', $att1->status instanceof AttendanceStatus ? $att1->status->value : (string) $att1->status);
        $this->assertEquals('2026-09-21', $att1->date?->format('Y-m-d'));

        // Verify late record created with auto arrival timestamp
        $att2 = Attendance::where('student_id', $this->student2->id)
            ->where('timetable_id', $this->timetable->id)
            ->first();
        $this->assertNotNull($att2);
        $this->assertEquals('late', $att2->status instanceof AttendanceStatus ? $att2->status->value : (string) $att2->status);
        $this->assertEquals('09:25', $att2->late_arrival_time);

        // Verify absence notification dispatched to guardian
        Notification::assertSentTo($this->guardian, SessionAbsenceNotification::class);

        Carbon::setTestNow();
    }

    public function test_guardian_children_list_and_attendances_retrieval(): void
    {
        $token = $this->guardianUser->createToken('test')->plainTextToken;

        // 1. Children list
        $childrenResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/guardian/children');

        $childrenResponse->assertOk()
            ->assertJsonPath('success', true);

        $children = $childrenResponse->json('children');
        $this->assertCount(2, $children);
        $this->assertEquals('Adam Alaoui', $children[0]['full_name']);

        // 2. Create attendances for student 1
        Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student1->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Absent,
            'justification_status' => JustificationStatus::Pending,
        ]);

        // 3. Retrieve student 1 attendances
        $attendancesResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/guardian/students/{$this->student1->id}/attendances");

        $attendancesResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('student.id', $this->student1->id);

        $data = $attendancesResponse->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('absent', $data[0]['status']);
        $this->assertEquals('Science & Physics', $data[0]['subject']['name']);
    }

    public function test_guardian_justification_submission_and_supervisor_notification(): void
    {
        Storage::fake('public');

        $attendance = Attendance::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student1->id,
            'classroom_id' => $this->classroom->id,
            'academic_year_id' => $this->academicYear->id,
            'timetable_id' => $this->timetable->id,
            'date' => '2026-09-21',
            'status' => AttendanceStatus::Absent,
        ]);

        $token = $this->guardianUser->createToken('test')->plainTextToken;
        $file = UploadedFile::fake()->create('medical_note.pdf', 300, 'application/pdf');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/guardian/attendances/{$attendance->id}/justify", [
                'reason' => 'Severe seasonal allergy medical rest.',
                'attachment' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.justification_status', 'pending');

        $attendance->refresh();
        $this->assertEquals('pending', $attendance->justification_status->value);
        $this->assertEquals('Severe seasonal allergy medical rest.', $attendance->guardian_justification_note);
        $this->assertNotNull($attendance->guardian_justification_attachment);
        Storage::disk('public')->assertExists($attendance->guardian_justification_attachment);

        // Verify supervisor was notified in database
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->supervisor->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_receipt_pdf_downloading_with_authorization_check(): void
    {
        $feeType = FeeType::create([
            'school_id' => $this->school->id,
            'name' => 'Monthly Tuition',
            'default_amount' => 1800.00,
        ]);

        $invoice = Invoice::create([
            'school_id' => $this->school->id,
            'student_id' => $this->student1->id,
            'academic_year_id' => $this->academicYear->id,
            'invoice_number' => 'INV-2026-001',
            'title' => 'September Tuition',
            'total_amount' => 1800.00,
            'paid_amount' => 1800.00,
            'status' => 'paid',
            'due_date' => '2026-09-05',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'fee_type_id' => $feeType->id,
            'description' => 'Tuition Sept 2026',
            'amount' => 1800.00,
            'quantity' => 1,
        ]);

        $payment = Payment::create([
            'school_id' => $this->school->id,
            'invoice_id' => $invoice->id,
            'amount' => 1800.00,
            'payment_method' => PaymentMethod::Cash,
            'payment_date' => '2026-09-05',
            'receipt_number' => 'REC-MOBILE-001',
        ]);

        // 1. Authorized Guardian can download the receipt
        $authorizedToken = $this->guardianUser->createToken('test')->plainTextToken;
        $authorizedResponse = $this->withHeader('Authorization', 'Bearer '.$authorizedToken)
            ->get("/api/v1/guardian/receipts/{$payment->id}/download");

        $authorizedResponse->assertOk();
        $this->assertEquals('application/pdf', $authorizedResponse->headers->get('content-type'));

        // 2. Another unrelated Guardian is rejected with 403 Forbidden
        $otherGuardian = Guardian::create([
            'school_id' => $this->school->id,
            'first_name' => 'Kareem',
            'last_name' => 'Zahidi',
            'phone' => '+212699887766',
            'relationship_type' => 'father',
        ]);

        $otherGuardianUser = User::create([
            'school_id' => $this->school->id,
            'guardian_id' => $otherGuardian->id,
            'name' => 'Kareem Zahidi',
            'email' => 'kareem.zahidi@example.com',
            'password' => Hash::make('Other@2026'),
            'role' => 'guardian',
            'is_active' => true,
        ]);

        $unauthorizedToken = $otherGuardianUser->createToken('test')->plainTextToken;
        $forbiddenResponse = $this->withHeader('Authorization', 'Bearer '.$unauthorizedToken)
            ->get("/api/v1/guardian/receipts/{$payment->id}/download");

        $forbiddenResponse->assertForbidden();
    }
}
