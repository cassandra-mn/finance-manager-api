<?php

namespace Tests\Feature\Api\V1\Insights;

use App\Enum\AccountType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashFlowForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_access_cash_flow_forecast(): void
    {
        $this->getJson('/api/v1/insights/cash-flow-forecast')->assertUnauthorized();
    }

    public function test_returns_a_three_month_forecast_by_default(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 50000]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/cash-flow-forecast');

        $response->assertOk()
            ->assertJsonPath('reference_period.months', 3)
            ->assertJsonPath('reference_period.from', '2026-08')
            ->assertJsonPath('reference_period.to', '2026-10')
            ->assertJsonPath('reference_period.starting_balance_cents', 50000)
            ->assertJsonCount(3, 'data');
    }

    public function test_summary_flags_months_with_negative_projected_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 10000]);

        Transaction::factory()->for($user)->for($account)
            ->expense()
            ->create(['amount_cents' => 50000, 'due_date' => '2026-08-20']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/cash-flow-forecast?months=1');

        $response->assertOk()
            ->assertJsonPath('summary.ending_balance_cents', -40000)
            ->assertJsonPath('summary.months_with_negative_balance_count', 1);
    }

    public function test_months_is_validated_between_1_and_12(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/cash-flow-forecast?months=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('months');

        $this->getJson('/api/v1/insights/cash-flow-forecast?months=13')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('months');
    }

    public function test_another_users_transactions_never_appear_in_the_forecast(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 0]);

        Transaction::factory()->for($userB)->for($accountB)
            ->expense()
            ->create(['amount_cents' => 99999, 'due_date' => '2026-08-20']);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/insights/cash-flow-forecast');

        $response->assertOk()->assertJsonPath('summary.total_projected_expense_cents', 0);
    }
}
