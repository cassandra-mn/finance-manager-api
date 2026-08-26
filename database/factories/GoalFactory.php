<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'name' => fake()->words(3, true),
            'target_cents' => fake()->numberBetween(100000, 5000000),
            'target_date' => null,
            'color' => fake()->safeHexColor(),
            'icon' => 'PiggyBank',
            'notes' => null,
        ];
    }
}
