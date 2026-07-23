<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory()->expense(),
            'amount_cents' => fake()->numberBetween(10000, 200000),
            'reference_month' => Carbon::today()->month,
            'reference_year' => Carbon::today()->year,
        ];
    }

    public function forPeriod(int $month, int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_month' => $month,
            'reference_year' => $year,
        ]);
    }
}
