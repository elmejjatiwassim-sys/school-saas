<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' School';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'price_per_student' => 1.50,
            'monthly_minimum_charge' => 300.00,
            'subscription_status' => 'active',
            'billing_start_date' => '2020-01-01',
            'is_active' => true,
        ];
    }
}
