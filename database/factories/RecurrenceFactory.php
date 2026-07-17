<?php

namespace Database\Factories;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurrence>
 */
class RecurrenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'type' => fake()->randomElement(TransactionType::cases()),
            'entry_type' => fake()->randomElement([TransactionEntryType::FIXED, TransactionEntryType::VARIABLE]),
            'description' => fake()->words(3, true),
            'amount_cents' => fake()->numberBetween(1000, 100000),
            'frequency' => fake()->randomElement(RecurrenceFrequency::cases()),
            'interval' => 1,
            'start_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'end_date' => null,
            'is_active' => true,
        ];
    }
}
