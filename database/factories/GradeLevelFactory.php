<?php

namespace Database\Factories;

use App\Models\GradeLevel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeLevel>
 */
class GradeLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = fake()->numberBetween(1, 12);

        return [
            'school_id' => School::factory(),
            'name' => "Grade {$grade}",
            'code' => "G{$grade}",
        ];
    }
}
