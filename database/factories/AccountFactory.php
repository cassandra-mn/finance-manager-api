<?php

namespace Database\Factories;

use App\Enum\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(AccountType::cases()),
            'initial_balance_cents' => fake()->numberBetween(0, 500000),
            'color' => fake()->safeHexColor(),
            'is_active' => true,
        ];
    }
}
