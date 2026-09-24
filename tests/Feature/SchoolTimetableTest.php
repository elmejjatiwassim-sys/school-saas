<?php

namespace Tests\Feature;

use App\Enums\DayOfWeek;
use App\Filament\Resources\TimetableResource\Pages\CreateTimetable;
use App\Filament\Resources\TimetableResource\Pages\ListTimetables;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Subject;
use App\Models\Timetable;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolTimetableTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected AcademicYear $academicYear;

    protected GradeLevel $gradeLevel;

    protected Classroom $classroom1;

    protected Classroom $classroom2;

    protected Subject $math;

    protected Subject $physics;

    protected User $teacher1;

    protected User $teacher2;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'name' => 'Lycée Ibn Khaldoun',
            'code' => 'IBNK',
            'slug' => 'ibn-khaldoun',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'school_id' => $this->school->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);

        $this->gradeLevel = GradeLevel::create([
            'school_id' => $this->school->id,
            'name' => 'Tronc Commun Scientifique',
            'code' => 'TCS',
        ]);

        $this->classroom1 = Classroom::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => 'TCS 1',
            'capacity' => 35,
        ]);

        $this->classroom2 = Classroom::create([
            'school_id' => $this->school->id,
            'academic_year_id' => $this->academicYear->id,
            'grade_level_id' => $this->gradeLevel->id,
            'name' => 'TCS 2',
            'capacity' => 35,
        ]);

        $this->math = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'Mathématiques',
            'code' => 'MATH',
            'coefficient' => 7.00,
        ]);

        $this->physics = Subject::create([
            'school_id' => $this->school->id,
            'name' => 'Physique-Chimie',
            'code' => 'PC',
            'coefficient' => 5.00,
        ]);

        $this->admin = User::create([
            'school_id' => $this->school->id,
            'name' => 'Principal Ibn Khaldoun',
            'username' => 'IBNK-0001',
            'email' => 'principal@ibnkhaldoun.ma',
            'password' => Hash::make('Password123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->teacher1 = User::create([
            'school_id' => $this->school->id,
            'name' => 'Prof. Mohammed Alami',
            'username' => 'IBNK-1001',
            'email' => 'alami@ibnkhaldoun.ma',
            'password' => Hash::make('Password123!'),
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->teacher2 = User::create([
            'school_id' => $this->school->id,
            'name' => 'Prof. Fatima Zahraoui',
            'username' => 'IBNK-1002',
            'email' => 'zahraoui@ibnkhaldoun.ma',
            'password' => Hash::make('Password123!'),
            'role' => 'teacher',
            'is_active' => true,
        ]);
    }

    public function test_can_create_timetable_slot_with_proper_relations(): void
    {
        $slot = Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Monday,
            'start_time' => '08:30',
            'end_time' => '10:30',
            'room_name' => 'Salle 12',
        ]);

        $this->assertInstanceOf(Timetable::class, $slot);
        $this->assertEquals($this->school->id, $slot->school->id);
        $this->assertEquals($this->classroom1->id, $slot->classroom->id);
        $this->assertEquals($this->math->id, $slot->subject->id);
        $this->assertEquals($this->teacher1->id, $slot->teacher->id);
        $this->assertEquals(DayOfWeek::Monday, $slot->day_of_week);
        $this->assertEquals('Salle 12', $slot->room_name);
    }

    public function test_cannot_create_timetable_slot_where_end_time_is_not_after_start_time(): void
    {
        $this->expectException(ValidationException::class);

        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Monday,
            'start_time' => '10:00',
            'end_time' => '09:00', // Invalid: end time before start time
        ]);
    }

    public function test_teacher_cannot_have_overlapping_time_slots_on_same_day(): void
    {
        // Teacher 1 teaches Math in Classroom 1 on Monday from 08:30 to 10:30
        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Monday,
            'start_time' => '08:30',
            'end_time' => '10:30',
            'room_name' => 'Salle 1',
        ]);

        // Attempting to schedule Teacher 1 in Classroom 2 on Monday from 09:30 to 11:30 should fail
        $this->expectException(ValidationException::class);

        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom2->id,
            'subject_id' => $this->physics->id,
            'teacher_id' => $this->teacher1->id, // Same teacher!
            'day_of_week' => DayOfWeek::Monday, // Same day!
            'start_time' => '09:30', // Overlaps with 08:30 - 10:30
            'end_time' => '11:30',
            'room_name' => 'Salle 2',
        ]);
    }

    public function test_teacher_can_have_back_to_back_and_different_day_sessions(): void
    {
        // Monday 08:30 - 10:30
        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Monday,
            'start_time' => '08:30',
            'end_time' => '10:30',
        ]);

        // Back-to-back: Monday 10:30 - 12:30 (starts exactly when previous ends -> Allowed!)
        $backToBack = Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom2->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Monday,
            'start_time' => '10:30',
            'end_time' => '12:30',
        ]);
        $this->assertNotNull($backToBack);

        // Different day: Tuesday 08:30 - 10:30 -> Allowed!
        $differentDay = Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Tuesday,
            'start_time' => '08:30',
            'end_time' => '10:30',
        ]);
        $this->assertNotNull($differentDay);
    }

    public function test_classroom_cannot_have_overlapping_time_slots_on_same_day(): void
    {
        // Classroom 1 has Math with Teacher 1 on Wednesday 14:00 - 16:00
        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Wednesday,
            'start_time' => '14:00',
            'end_time' => '16:00',
        ]);

        // Attempting to schedule Physics with Teacher 2 in Classroom 1 at 15:00 - 17:00 should fail
        $this->expectException(ValidationException::class);

        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id, // Same classroom!
            'subject_id' => $this->physics->id,
            'teacher_id' => $this->teacher2->id, // Different teacher
            'day_of_week' => DayOfWeek::Wednesday, // Same day!
            'start_time' => '15:00', // Overlaps with 14:00 - 16:00
            'end_time' => '17:00',
        ]);
    }

    public function test_room_cannot_be_double_booked_at_same_time(): void
    {
        // Lab 1 reserved for Classroom 1 on Thursday 08:30 - 10:30
        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->physics->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Thursday,
            'start_time' => '08:30',
            'end_time' => '10:30',
            'room_name' => 'Lab Physique',
        ]);

        // Attempting to reserve Lab 1 for Classroom 2 / Teacher 2 at 09:30 - 11:30 should fail
        $this->expectException(ValidationException::class);

        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom2->id,
            'subject_id' => $this->physics->id,
            'teacher_id' => $this->teacher2->id,
            'day_of_week' => DayOfWeek::Thursday,
            'start_time' => '09:30',
            'end_time' => '11:30',
            'room_name' => 'Lab Physique', // Same room!
        ]);
    }

    public function test_filament_create_timetable_prevents_conflicts_via_form_validation(): void
    {
        $this->actingAs($this->admin);
        Filament::setTenant($this->school);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Pre-existing slot
        Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Friday,
            'start_time' => '08:30',
            'end_time' => '10:30',
            'room_name' => 'Salle A',
        ]);

        // Livewire form submission with overlapping teacher slot
        Livewire::test(CreateTimetable::class)
            ->fillForm([
                'classroom_id' => $this->classroom2->id,
                'subject_id' => $this->physics->id,
                'teacher_id' => $this->teacher1->id, // Teacher 1 already busy
                'day_of_week' => DayOfWeek::Friday->value,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'room_name' => 'Salle B',
            ])
            ->call('create')
            ->assertHasFormErrors(['teacher_id']);

        // Livewire form submission with valid non-overlapping slot
        Livewire::test(CreateTimetable::class)
            ->fillForm([
                'classroom_id' => $this->classroom2->id,
                'subject_id' => $this->physics->id,
                'teacher_id' => $this->teacher2->id, // Teacher 2 free
                'day_of_week' => DayOfWeek::Friday->value,
                'start_time' => '10:30',
                'end_time' => '12:30',
                'room_name' => 'Salle B',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('timetables', [
            'classroom_id' => $this->classroom2->id,
            'teacher_id' => $this->teacher2->id,
            'day_of_week' => DayOfWeek::Friday->value,
            'start_time' => '10:30:00',
            'end_time' => '12:30:00',
        ]);
    }

    public function test_filament_timetable_table_lists_and_filters_slots(): void
    {
        $this->actingAs($this->admin);
        Filament::setTenant($this->school);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $slot = Timetable::create([
            'school_id' => $this->school->id,
            'classroom_id' => $this->classroom1->id,
            'subject_id' => $this->math->id,
            'teacher_id' => $this->teacher1->id,
            'day_of_week' => DayOfWeek::Saturday,
            'start_time' => '08:30',
            'end_time' => '10:30',
            'room_name' => 'Salle Amphithéâtre',
        ]);

        Livewire::test(ListTimetables::class)
            ->assertSuccessful()
            ->assertSee($this->classroom1->name)
            ->assertSee($this->math->name)
            ->assertSee($this->teacher1->name)
            ->assertSee('Salle Amphithéâtre')
            ->assertSee('08:30 - 10:30');
    }
}
