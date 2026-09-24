<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\GuardianRelationshipType;
use App\Enums\StudentStatus;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentsAndParentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_belongs_to_school_and_has_many_students(): void
    {
        $school = School::factory()->create();
        $guardian = Guardian::factory()->create([
            'school_id' => $school->id,
            'relationship_type' => GuardianRelationshipType::Father,
        ]);

        $classroom = Classroom::factory()->create(['school_id' => $school->id]);

        $student1 = Student::factory()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $classroom->id,
        ]);

        $student2 = Student::factory()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $classroom->id,
        ]);

        $this->assertTrue($guardian->school->is($school));
        $this->assertCount(2, $guardian->students);
        $this->assertTrue($guardian->students->contains($student1));
        $this->assertTrue($guardian->students->contains($student2));
        $this->assertTrue($school->guardians->contains($guardian));
    }

    public function test_student_belongs_to_school_guardian_and_classroom(): void
    {
        $school = School::factory()->create();
        $guardian = Guardian::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create(['school_id' => $school->id]);

        $student = Student::factory()->create([
            'school_id' => $school->id,
            'guardian_id' => $guardian->id,
            'classroom_id' => $classroom->id,
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        $this->assertTrue($student->school->is($school));
        $this->assertTrue($student->guardian->is($guardian));
        $this->assertTrue($student->classroom->is($classroom));
        $this->assertTrue($classroom->students->contains($student));
        $this->assertTrue($school->students->contains($student));
    }

    public function test_student_and_guardian_resources_accessible_by_tenant_user(): void
    {
        $school = School::factory()->create(['slug' => 'al-amal-test']);
        $user = User::factory()->create(['school_id' => $school->id]);

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/guardians")
            ->assertSuccessful();

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/students")
            ->assertSuccessful();
    }

    public function test_students_and_guardians_isolated_between_schools(): void
    {
        $school1 = School::factory()->create(['slug' => 'school-one']);
        $user1 = User::factory()->create(['school_id' => $school1->id]);

        $school2 = School::factory()->create(['slug' => 'school-two']);

        $guardian1 = Guardian::factory()->create([
            'school_id' => $school1->id,
            'first_name' => 'UniqueGuardianSchool1',
        ]);

        $guardian2 = Guardian::factory()->create([
            'school_id' => $school2->id,
            'first_name' => 'UniqueGuardianSchool2',
        ]);

        $classroom1 = Classroom::factory()->create(['school_id' => $school1->id]);
        $classroom2 = Classroom::factory()->create(['school_id' => $school2->id]);

        $student1 = Student::factory()->create([
            'school_id' => $school1->id,
            'guardian_id' => $guardian1->id,
            'classroom_id' => $classroom1->id,
            'first_name' => 'UniqueStudentSchool1',
        ]);

        $student2 = Student::factory()->create([
            'school_id' => $school2->id,
            'guardian_id' => $guardian2->id,
            'classroom_id' => $classroom2->id,
            'first_name' => 'UniqueStudentSchool2',
        ]);

        $responseGuardians = $this->actingAs($user1)->get("/admin/{$school1->slug}/guardians");
        $responseGuardians->assertSuccessful();
        $responseGuardians->assertSee('UniqueGuardianSchool1');
        $responseGuardians->assertDontSee('UniqueGuardianSchool2');

        $responseStudents = $this->actingAs($user1)->get("/admin/{$school1->slug}/students");
        $responseStudents->assertSuccessful();
        $responseStudents->assertSee('UniqueStudentSchool1');
        $responseStudents->assertDontSee('UniqueStudentSchool2');
    }

    public function test_database_seeder_seeds_five_dummy_students_with_guardians(): void
    {
        $this->seed(DatabaseSeeder::class);

        $school = School::where('slug', 'al-amal')->firstOrFail();

        $this->assertEquals(5, $school->guardians()->count());
        $this->assertEquals(5, $school->students()->count());

        $firstStudent = $school->students()->first();
        $this->assertNotNull($firstStudent->guardian);
        $this->assertNotNull($firstStudent->classroom);
        $this->assertEquals(StudentStatus::Active, $firstStudent->status);
    }
}
