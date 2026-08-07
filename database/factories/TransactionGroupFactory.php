<?php

namespace Database\Factories;

use App\Enum\TransactionGroupStatus;
use App\Enum\TransactionGroupType;
use App\Models\Account;
use App\Models\TransactionGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TransactionGroup>
 */
class TransactionGroupFactory extends Factory
{
    public function definition(): array
    {
        $referenceMonth = Carbon::today()->startOfMonth();

        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory()->creditCard(),
            'type' => TransactionGroupType::CREDIT_CARD_INVOICE,
            'name' => null,
            'reference_month' => $referenceMonth,
            'closing_date' => $referenceMonth->copy()->subMonth()->day(25),
            'due_date' => $referenceMonth->copy()->day(5),
            'status' => TransactionGroupStatus::OPEN,
            'payment_account_id' => null,
            'payment_transaction_id' => null,
            'paid_at' => null,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TransactionGroupStatus::CLOSED]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionGroupStatus::PAID,
            'paid_at' => Carbon::now(),
        ]);
    }

    public function partiallyPaid(int $paidAmountCents): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TransactionGroupStatus::PARTIALLY_PAID,
            'paid_amount_cents' => $paidAmountCents,
            'paid_at' => Carbon::now(),
        ]);
    }
}
