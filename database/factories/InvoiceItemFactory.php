<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->sentence(3),
            'fee_type_id' => null,
            'amount' => fake()->randomFloat(2, 100, 2000),
            'quantity' => 1,
        ];
    }
}
