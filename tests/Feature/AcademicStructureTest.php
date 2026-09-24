<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_models_belong_to_school(): void
    {
        $school = School::factory()->create();

        $academicYear = AcademicYear::factory()->create(['school_id' => $school->id]);
        $gradeLevel = GradeLevel::factory()->create(['school_id' => $school->id]);
        $classroom = Classroom::factory()->create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);
        $subject = Subject::factory()->create(['school_id' => $school->id]);

        $this->assertTrue($academicYear->school->is($school));
        $this->assertTrue($gradeLevel->school->is($school));
        $this->assertTrue($classroom->school->is($school));
        $this->assertTrue($subject->school->is($school));

        $this->assertTrue($school->academicYears->contains($academicYear));
        $this->assertTrue($school->gradeLevels->contains($gradeLevel));
        $this->assertTrue($school->classrooms->contains($classroom));
        $this->assertTrue($school->subjects->contains($subject));
    }

    public function test_relationships_between_classroom_grade_level_and_academic_year(): void
    {
        $school = School::factory()->create();

        $academicYear = AcademicYear::factory()->create([
            'school_id' => $school->id,
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
        ]);

        $gradeLevel = GradeLevel::factory()->create([
            'school_id' => $school->id,
            'name' => 'Grade 10',
            'code' => 'G10',
        ]);

        $classroom = Classroom::factory()->create([
            'school_id' => $school->id,
            'academic_year_id' => $academicYear->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'Grade 10-A',
            'capacity' => 30,
        ]);

        $this->assertTrue($classroom->academicYear->is($academicYear));
        $this->assertTrue($classroom->gradeLevel->is($gradeLevel));
        $this->assertTrue($academicYear->classrooms->contains($classroom));
        $this->assertTrue($gradeLevel->classrooms->contains($classroom));
    }

    public function test_academic_resources_pages_are_accessible_by_tenant_user(): void
    {
        $school = School::factory()->create(['slug' => 'oasis-school']);
        $user = User::factory()->create(['school_id' => $school->id]);

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/academic-years")
            ->assertSuccessful();

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/grade-levels")
            ->assertSuccessful();

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/classrooms")
            ->assertSuccessful();

        $this->actingAs($user)
            ->get("/admin/{$school->slug}/subjects")
            ->assertSuccessful();
    }

    public function test_resources_are_isolated_between_schools(): void
    {
        $school1 = School::factory()->create(['name' => 'School Alpha', 'slug' => 'school-alpha']);
        $user1 = User::factory()->create(['school_id' => $school1->id]);

        $school2 = School::factory()->create(['name' => 'School Beta', 'slug' => 'school-beta']);

        $academicYear1 = AcademicYear::factory()->create([
            'school_id' => $school1->id,
            'name' => 'Year-Alpha-2026',
        ]);

        $academicYear2 = AcademicYear::factory()->create([
            'school_id' => $school2->id,
            'name' => 'Year-Beta-2026',
        ]);

        $response = $this->actingAs($user1)->get("/admin/{$school1->slug}/academic-years");
        $response->assertSuccessful();
        $response->assertSee('Year-Alpha-2026');
        $response->assertDontSee('Year-Beta-2026');
    }
}
