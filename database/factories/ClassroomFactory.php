<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\GradeLevel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $section = fake()->randomElement(['A', 'B', 'C', 'D']);

        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes) => AcademicYear::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'grade_level_id' => fn (array $attributes) => GradeLevel::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'name' => "Class {$section}",
            'capacity' => fake()->numberBetween(20, 35),
        ];
    }
}
