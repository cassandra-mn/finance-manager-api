<?php

namespace Tests\Feature\Services\Insights;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\DebtPayoffPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DebtPayoffPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_months_to_payoff_with_no_interest(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 0]);

        Transaction::factory()->for($user)->for($account)->expense()->paid()
            ->create(['amount_cents' => 100000]);

        $plan = app(DebtPayoffPlanService::class)->build($user->id, $account->id, 25000, 0.0);

        $this->assertSame(100000, $plan['current_debt_cents']);
        $this->assertSame(4, $plan['months_to_payoff']);
        $this->assertSame(0, $plan['total_interest_cents']);
        $this->assertSame(100000, $plan['total_paid_cents']);
        $this->assertCount(4, $plan['schedule']);
        $this->assertSame(0, $plan['schedule'][3]['ending_balance_cents']);
    }

    public function test_projects_months_to_payoff_with_interest_and_computes_total_interest(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 0]);

        Transaction::factory()->for($user)->for($account)->expense()->paid()
            ->create(['amount_cents' => 500000]);

        $plan = app(DebtPayoffPlanService::class)->build($user->id, $account->id, 50000, 24.0);

        $this->assertSame(500000, $plan['current_debt_cents']);
        $this->assertGreaterThan(0, $plan['total_interest_cents']);
        $this->assertSame($plan['current_debt_cents'] + $plan['total_interest_cents'], $plan['total_paid_cents']);

        $last = end($plan['schedule']);
        $this->assertSame(0, $last['ending_balance_cents']);
    }

    public function test_throws_when_account_has_no_debt(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 100000]);

        $this->expectException(ValidationException::class);

        app(DebtPayoffPlanService::class)->build($user->id, $account->id, 10000, 0.0);
    }

    public function test_throws_when_monthly_payment_does_not_cover_interest(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 0]);

        Transaction::factory()->for($user)->for($account)->expense()->paid()
            ->create(['amount_cents' => 1000000]);

        $this->expectException(ValidationException::class);

        // 60% ao ano sobre R$10.000 dá ~R$500/mês de juros: um pagamento de
        // R$100 nunca cobre nem os juros, a dívida cresceria pra sempre.
        app(DebtPayoffPlanService::class)->build($user->id, $account->id, 10000, 60.0);
    }

    public function test_throws_when_payoff_would_take_longer_than_thirty_years(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 0]);

        Transaction::factory()->for($user)->for($account)->expense()->paid()
            ->create(['amount_cents' => 5000000]);

        $this->expectException(ValidationException::class);

        // Pagamento pouco acima do mínimo de juros: quita, mas levaria mais
        // de 30 anos.
        app(DebtPayoffPlanService::class)->build($user->id, $account->id, 42000, 10.0);
    }

    public function test_another_users_account_is_not_found(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create(['initial_balance_cents' => 0]);

        Transaction::factory()->for($userB)->for($accountB)->expense()->paid()
            ->create(['amount_cents' => 100000]);

        $this->expectException(ValidationException::class);

        app(DebtPayoffPlanService::class)->build($userA->id, $accountB->id, 10000, 0.0);
    }
}
