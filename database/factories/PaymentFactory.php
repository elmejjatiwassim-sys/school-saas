<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'invoice_id' => fn (array $attributes) => Invoice::factory()->create([
                'school_id' => $attributes['school_id'],
            ])->id,
            'amount' => 1500.00,
            'payment_date' => now()->toDateString(),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'receipt_number' => 'REC-'.date('Ymd').'-'.fake()->unique()->numerify('#####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
