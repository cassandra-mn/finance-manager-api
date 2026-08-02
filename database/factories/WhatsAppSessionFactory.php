<?php

namespace Database\Factories;

use App\Enum\WhatsAppSessionState;
use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppSession>
 */
class WhatsAppSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone_number' => '+55'.fake()->numerify('###########'),
            'user_id' => User::factory(),
            'state' => WhatsAppSessionState::IDLE,
            'context' => null,
        ];
    }

    public function awaitingConfirmation(array $context): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => WhatsAppSessionState::AWAITING_CONFIRMATION,
            'context' => $context,
        ]);
    }

    public function unlinked(): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => null]);
    }
}
