<?php

namespace Database\Factories;

use App\Enums\GuardianRelationshipType;
use App\Models\Guardian;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $relationship = fake()->randomElement(GuardianRelationshipType::cases());
        $gender = match ($relationship) {
            GuardianRelationshipType::Father => 'male',
            GuardianRelationshipType::Mother => 'female',
            default => fake()->randomElement(['male', 'female']),
        };

        return [
            'school_id' => School::factory(),
            'first_name' => fake()->firstName($gender),
            'last_name' => fake()->lastName(),
            'cin' => strtoupper(fake()->bothify('??######')),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'relationship_type' => $relationship,
        ];
    }
}
