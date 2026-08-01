<?php

namespace Database\Factories;

use App\Enum\BankConnectionStatus;
use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankConnection>
 */
class BankConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pluggy_item_id' => fake()->uuid(),
            'institution_id' => fake()->numerify('###'),
            'institution_name' => fake()->company(),
            'status' => BankConnectionStatus::UPDATED,
            'last_synced_at' => null,
            'last_sync_error' => null,
        ];
    }

    public function status(BankConnectionStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
