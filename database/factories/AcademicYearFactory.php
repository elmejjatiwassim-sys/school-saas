<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->numberBetween(2020, 2030);
        $startDate = fake()->dateTimeBetween("{$startYear}-08-01", "{$startYear}-09-15");
        $endDate = (clone $startDate)->modify('+9 months');

        return [
            'school_id' => School::factory(),
            'name' => "{$startYear}-".($startYear + 1),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
        ]);
    }
}
