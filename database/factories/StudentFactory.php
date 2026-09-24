<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'school_id' => School::factory(),
            'guardian_id' => fn (array $attributes) => Guardian::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'classroom_id' => fn (array $attributes) => Classroom::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'massar_code' => strtoupper(fake()->unique()->bothify('?#########')),
            'first_name' => fake()->firstName($gender->value),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-6 years')->format('Y-m-d'),
            'gender' => $gender,
            'registration_number' => 'REG-'.fake()->unique()->numerify('#####'),
            'opening_balance' => 0.00,
            'status' => StudentStatus::Active,
        ];
    }
}
