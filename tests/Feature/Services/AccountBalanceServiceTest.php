<?php

namespace Tests\Feature\Services;

use App\Models\Account;
use App\Models\User;
use App\Services\AccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_is_initial_balance_when_there_are_no_paid_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 10000]);

        $account->transactions()->create([
            'user_id' => $user->id,
            'category_id' => null,
            'type' => 'expense',
            'entry_type' => 'single',
            'status' => 'pending',
            'description' => 'Pendente',
            'amount_cents' => 5000,
            'due_date' => now(),
        ]);

        $balance = app(AccountBalanceService::class)->calculateCurrentBalance($account->fresh());

        $this->assertSame(10000, $balance->cents);
    }

    public function test_balance_adds_paid_income_and_subtracts_paid_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 10000]);

        $account->transactions()->create([
            'user_id' => $user->id,
            'type' => 'income',
            'entry_type' => 'single',
            'status' => 'paid',
            'description' => 'Salário',
            'amount_cents' => 300000,
            'due_date' => now(),
            'paid_at' => now(),
        ]);

        $account->transactions()->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'entry_type' => 'single',
            'status' => 'paid',
            'description' => 'Aluguel',
            'amount_cents' => 120000,
            'due_date' => now(),
            'paid_at' => now(),
        ]);

        $balance = app(AccountBalanceService::class)->calculateCurrentBalance($account->fresh());

        // 10000 (inicial) + 300000 (receita paga) - 120000 (despesa paga)
        $this->assertSame(190000, $balance->cents);
    }

    public function test_a_partially_paid_expense_only_subtracts_the_amount_actually_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 10000]);

        // Conta de R$200 (20000 centavos), só R$50 (5000 centavos) pagos —
        // o restante não é uma pendência rastreada, então não deve sair do saldo.
        $account->transactions()->create([
            'user_id' => $user->id,
            'type' => 'expense',
            'entry_type' => 'single',
            'status' => 'partially_paid',
            'description' => 'Conta parcialmente paga',
            'amount_cents' => 20000,
            'paid_amount_cents' => 5000,
            'due_date' => now(),
            'paid_at' => now(),
        ]);

        $balance = app(AccountBalanceService::class)->calculateCurrentBalance($account->fresh());

        // 10000 (inicial) - 5000 (só a parte paga, não os 20000 cheios)
        $this->assertSame(5000, $balance->cents);
    }
}
