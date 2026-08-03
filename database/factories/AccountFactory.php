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
            'credit_limit_cents' => null,
            'invoice_due_day' => null,
            'invoice_closing_day' => null,
            'color' => fake()->safeHexColor(),
            'is_active' => true,
        ];
    }

    public function creditCard(int $dueDay = 10, ?int $closingDay = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountType::CREDIT_CARD,
            'credit_limit_cents' => fake()->numberBetween(100000, 1000000),
            'invoice_due_day' => $dueDay,
            'invoice_closing_day' => $closingDay,
        ]);
    }
}
