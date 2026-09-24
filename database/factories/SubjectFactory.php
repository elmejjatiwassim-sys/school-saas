<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Mathematics', 'English Literature', 'Physics', 'Chemistry',
            'Biology', 'History', 'Geography', 'Computer Science',
            'Art', 'Music', 'Physical Education', 'French',
        ]);

        return [
            'school_id' => School::factory(),
            'name' => $name,
            'code' => strtoupper(substr(str_replace(' ', '', $name), 0, 4)),
            'coefficient' => fake()->randomElement([1.00, 1.50, 2.00, 3.00]),
        ];
    }
}
