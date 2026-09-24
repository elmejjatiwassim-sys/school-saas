<?php

namespace Tests\Feature;

use App\Enums\AttendanceSession;
use App\Enums\AttendanceStatus;
use App\Filament\Pages\TakeAttendance;
use App\Filament\Resources\StudentResource\Pages\EditStudent;
use App\Filament\Resources\StudentResource\RelationManagers\AttendanceRelationManager;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Database\Seeders\AttendanceSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_record_belongs_to_school_student_classroom_and_academic_year(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        $record = AttendanceRecord::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $academicYear->id,
            'date' => '2026-09-24',
            'session' => AttendanceSession::Morning,
            'status' => AttendanceStatus::Present,
            'remarks' => 'On time',
        ]);

        $this->assertTrue($record->school->is($school));
        $this->assertTrue($record->student->is($student));
        $this->assertTrue($record->classroom->is($classroom));
        $this->assertTrue($record->academicYear->is($academicYear));
        $this->assertEquals(AttendanceSession::Morning, $record->session);
        $this->assertEquals(AttendanceStatus::Present, $record->status);

        $this->assertTrue($school->attendanceRecords->contains($record));
        $this->assertTrue($student->attendanceRecords->contains($record));
        $this->assertTrue($classroom->attendanceRecords->contains($record));
    }

    public function test_unique_constraint_prevents_duplicate_attendance_record_for_same_session(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);
        $teacher = User::factory()->create(['school_id' => $school->id, 'role' => 'teacher']);
        $subject = Subject::factory()->create(['school_id' => $school->id]);
        $timetable = Timetable::factory()->create([
            'school_id' => $school->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        AttendanceRecord::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $academicYear->id,
            'timetable_id' => $timetable->id,
            'date' => '2026-09-24',
            'session' => AttendanceSession::Morning,
            'status' => AttendanceStatus::Present,
        ]);

        $this->expectException(QueryException::class);

        AttendanceRecord::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $academicYear->id,
            'timetable_id' => $timetable->id,
            'date' => '2026-09-24',
            'session' => AttendanceSession::Morning,
            'status' => AttendanceStatus::Absent,
        ]);
    }

    public function test_take_attendance_page_and_records_resource_accessible_by_tenant_user(): void
    {
        $school = School::factory()->create(['slug' => 'test-school']);
        $user = User::factory()->create(['school_id' => $school->id]);

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/take-attendance")
            ->assertSuccessful();

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/attendance-records")
            ->assertSuccessful();
    }

    public function test_rapid_attendance_livewire_sheet_flow(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);

        $student1 = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'status' => 'active']);
        $student2 = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'status' => 'active']);

        $this->actingAs($user);
        Filament::setTenant($school);

        Livewire::test(TakeAttendance::class)
            ->set('classroom_id', $classroom->id)
            ->set('date', '2026-09-24')
            ->set('session', 'morning')
            ->call('markAllPresent')
            ->assertSet("studentsAttendance.{$student1->id}.status", 'present')
            ->assertSet("studentsAttendance.{$student2->id}.status", 'present')
            // Change student 2 to absent
            ->set("studentsAttendance.{$student2->id}.status", 'absent')
            ->set("studentsAttendance.{$student2->id}.remarks", 'Malade')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $school->id,
            'student_id' => $student1->id,
            'classroom_id' => $classroom->id,
            'session' => 'morning',
            'status' => 'present',
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'school_id' => $school->id,
            'student_id' => $student2->id,
            'classroom_id' => $classroom->id,
            'session' => 'morning',
            'status' => 'absent',
            'remarks' => 'Malade',
        ]);

        $record1 = AttendanceRecord::where('student_id', $student1->id)->first();
        $this->assertNotNull($record1);
        $this->assertSame('2026-09-24', $record1->date->toDateString());
    }

    public function test_invoices_have_unique_constraint_preventing_duplicate_academic_month(): void
    {
        $school = School::factory()->create();
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $student = Student::factory()->create(['school_id' => $school->id]);

        Invoice::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'invoice_number' => 'INV-001',
            'billing_month' => 9,
            'billing_year' => 2026,
            'title' => 'Sept 2026',
            'total_amount' => 1500,
            'due_date' => '2026-09-05',
        ]);

        $this->expectException(QueryException::class);

        Invoice::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'invoice_number' => 'INV-002',
            'billing_month' => 9,
            'billing_year' => 2026,
            'title' => 'Duplicate Sept 2026',
            'total_amount' => 1500,
            'due_date' => '2026-09-05',
        ]);
    }

    public function test_student_attendance_relation_manager_renders_and_computes_stats(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id]);

        // Create 1 present and 2 absences
        AttendanceRecord::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $academicYear->id,
            'date' => '2026-09-21',
            'session' => AttendanceSession::Morning,
            'status' => AttendanceStatus::Present,
        ]);

        AttendanceRecord::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $academicYear->id,
            'date' => '2026-09-22',
            'session' => AttendanceSession::Morning,
            'status' => AttendanceStatus::Absent,
        ]);

        AttendanceRecord::create([
            'school_id' => $school->id,
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $academicYear->id,
            'date' => '2026-09-23',
            'session' => AttendanceSession::Afternoon,
            'status' => AttendanceStatus::Excused,
        ]);

        $this->actingAs($user);
        Filament::setTenant($school);

        Livewire::test(AttendanceRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditStudent::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($student->attendanceRecords)
            ->assertCountTableRecords(3);
    }

    public function test_attendance_seeder_successfully_populates_records(): void
    {
        $school = School::factory()->create(['slug' => 'al-amal']);
        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id, 'is_current' => true]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id, 'academic_year_id' => $academicYear->id]);
        $student = Student::factory()->create(['school_id' => $school->id, 'classroom_id' => $classroom->id, 'status' => 'active']);

        $this->seed(AttendanceSeeder::class);

        $this->assertGreaterThan(0, AttendanceRecord::where('student_id', $student->id)->count());
    }
}
