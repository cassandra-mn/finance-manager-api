<?php

namespace Database\Factories;

use App\Enum\StatementImportFormat;
use App\Models\Account;
use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatementImport>
 */
class StatementImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'format' => fake()->randomElement(StatementImportFormat::cases()),
            'original_filename' => fake()->word().'.'.fake()->randomElement(['ofx', 'csv']),
            'transactions_created' => fake()->numberBetween(0, 50),
            'transactions_skipped' => fake()->numberBetween(0, 5),
        ];
    }
}
