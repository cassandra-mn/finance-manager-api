<?php

namespace Database\Factories;

use App\Enum\TransactionEntryType;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'type' => fake()->randomElement(TransactionType::cases()),
            'entry_type' => fake()->randomElement(TransactionEntryType::cases()),
            'status' => TransactionStatus::PENDING,
            'description' => fake()->words(3, true),
            'amount_cents' => fake()->numberBetween(500, 200000),
            'due_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'paid_at' => null,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::PAID,
            'paid_at' => Carbon::now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionStatus::PENDING,
            'due_date' => Carbon::today()->subDays(5),
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TransactionType::INCOME]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TransactionType::EXPENSE]);
    }
}
