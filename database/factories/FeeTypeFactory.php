<?php

namespace Database\Factories;

use App\Models\FeeType;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeType>
 */
class FeeTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            ['name' => 'Monthly Tuition (واجب شهري)', 'amount' => 1500.00, 'recurring' => true],
            ['name' => 'Registration Fee (رسوم التسجيل)', 'amount' => 1000.00, 'recurring' => false],
            ['name' => 'Insurance (تأمين مدرسي)', 'amount' => 300.00, 'recurring' => false],
            ['name' => 'School Transport (نقل مدرسي)', 'amount' => 500.00, 'recurring' => true],
        ];

        $choice = fake()->randomElement($types);

        return [
            'school_id' => School::factory(),
            'name' => $choice['name'],
            'default_amount' => $choice['amount'],
            'is_recurring_monthly' => $choice['recurring'],
        ];
    }
}
