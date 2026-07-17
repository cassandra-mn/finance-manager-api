<?php

namespace Database\Factories;

use App\Enum\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(TransactionType::cases()),
            'color' => fake()->safeHexColor(),
            'icon' => null,
        ];
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
