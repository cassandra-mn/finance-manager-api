<?php

namespace Tests\Feature\Api\V1\Insights;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DebtPayoffPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_debt_payoff_plan(): void
    {
        $this->getJson('/api/v1/insights/debt-payoff-plan?account_id=1&monthly_payment_cents=10000')
            ->assertUnauthorized();
    }

    public function test_account_id_and_monthly_payment_cents_are_required(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/debt-payoff-plan')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id', 'monthly_payment_cents']);
    }

    public function test_returns_the_payoff_schedule_for_an_account_with_debt(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 0]);

        Transaction::factory()->for($user)->for($account)->expense()->paid()
            ->create(['amount_cents' => 100000]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/insights/debt-payoff-plan?account_id={$account->id}&monthly_payment_cents=25000");

        $response->assertOk()
            ->assertJsonPath('summary.current_debt_cents', 100000)
            ->assertJsonPath('summary.months_to_payoff', 4)
            ->assertJsonPath('summary.total_interest_cents', 0)
            ->assertJsonCount(4, 'data');
    }

    public function test_returns_validation_error_when_account_has_no_debt(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 100000]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/insights/debt-payoff-plan?account_id={$account->id}&monthly_payment_cents=10000")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account_id');
    }

    public function test_returns_validation_error_when_payment_never_covers_the_debt(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['initial_balance_cents' => 0]);

        Transaction::factory()->for($user)->for($account)->expense()->paid()
            ->create(['amount_cents' => 1000000]);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/v1/insights/debt-payoff-plan?account_id={$account->id}&monthly_payment_cents=10000&annual_interest_rate_percentage=60",
        );

        $response->assertUnprocessable()->assertJsonValidationErrors('monthly_payment_cents');
    }

    public function test_another_users_account_returns_validation_error(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create(['initial_balance_cents' => 0]);

        Transaction::factory()->for($userB)->for($accountB)->expense()->paid()
            ->create(['amount_cents' => 100000]);

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/insights/debt-payoff-plan?account_id={$accountB->id}&monthly_payment_cents=10000")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account_id');
    }
}
