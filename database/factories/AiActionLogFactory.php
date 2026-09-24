<?php

namespace Database\Factories;

use App\Enums\AiActionLogStatus;
use App\Models\AiActionLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiActionLog>
 */
class AiActionLogFactory extends Factory
{
    protected $model = AiActionLog::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'action_type' => 'register_student',
            'payload' => [
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'classroom_name' => 'CP-A',
            ],
            'status' => AiActionLogStatus::PendingConfirmation,
            'confirmed_at' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function executed(): static
    {
        return $this->state(fn () => [
            'status' => AiActionLogStatus::Executed,
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => AiActionLogStatus::Cancelled,
            'confirmed_at' => null,
        ]);
    }
}
