<?php

namespace Tests\Feature\Api\V1;

use App\Enum\TransactionStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_zeroed_series_for_a_user_without_any_transactions(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cash-flow/evolution?reference_date=2026-07-15&months=3');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.period', '2026-05')
            ->assertJsonPath('data.0.income_cents', 0)
            ->assertJsonPath('data.0.expense_cents', 0)
            ->assertJsonPath('data.0.balance_cents', 0)
            ->assertJsonPath('data.2.period', '2026-07')
            ->assertJsonPath('summary.total_income_cents', 0)
            ->assertJsonPath('summary.total_expense_cents', 0)
            ->assertJsonPath('summary.total_balance_cents', 0);
    }

    public function test_does_not_leak_another_users_transactions_into_the_evolution(): void
    {
        $userWithoutTransactions = User::factory()->create();

        $otherUser = User::factory()->create();
        $account = Account::factory()->for($otherUser)->create();
        $category = Category::factory()->for($otherUser)->income()->create();
        Transaction::factory()->for($otherUser)->for($account)->for($category)->income()->paid()
            ->create(['amount_cents' => 500000, 'due_date' => '2026-07-10']);

        Sanctum::actingAs($userWithoutTransactions);

        $response = $this->getJson('/api/v1/cash-flow/evolution?reference_date=2026-07-15&months=1');

        $response->assertOk()
            ->assertJsonPath('data.0.income_cents', 0)
            ->assertJsonPath('data.0.expense_cents', 0)
            ->assertJsonPath('summary.total_income_cents', 0);
    }

    public function test_sums_income_and_expense_per_month_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $incomeCategory = Category::factory()->for($user)->income()->create();
        $expenseCategory = Category::factory()->for($user)->expense()->create();

        Transaction::factory()->for($user)->for($account)->for($incomeCategory)->income()->paid()
            ->create(['amount_cents' => 300000, 'due_date' => '2026-07-05']);
        Transaction::factory()->for($user)->for($account)->for($expenseCategory)->expense()->paid()
            ->create(['amount_cents' => 120000, 'due_date' => '2026-07-20']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cash-flow/evolution?reference_date=2026-07-15&months=1');

        $response->assertOk()
            ->assertJsonPath('data.0.period', '2026-07')
            ->assertJsonPath('data.0.income_cents', 300000)
            ->assertJsonPath('data.0.expense_cents', 120000)
            ->assertJsonPath('data.0.balance_cents', 180000)
            ->assertJsonPath('summary.total_income_cents', 300000)
            ->assertJsonPath('summary.total_expense_cents', 120000)
            ->assertJsonPath('summary.total_balance_cents', 180000);
    }

    public function test_excludes_cancelled_transactions_from_totals(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        Transaction::factory()->for($user)->for($account)->for($category)->expense()
            ->create(['status' => TransactionStatus::CANCELLED, 'amount_cents' => 90000, 'due_date' => '2026-07-10']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cash-flow/evolution?reference_date=2026-07-15&months=1');

        $response->assertOk()->assertJsonPath('data.0.expense_cents', 0);
    }

    public function test_excludes_transactions_outside_the_requested_range(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->income()->create();

        Transaction::factory()->for($user)->for($account)->for($category)->income()->paid()
            ->create(['amount_cents' => 100000, 'due_date' => '2026-04-30']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cash-flow/evolution?reference_date=2026-07-15&months=1');

        $response->assertOk()->assertJsonPath('data.0.income_cents', 0);
    }

    public function test_months_parameter_controls_the_size_of_the_series(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cash-flow/evolution?reference_date=2026-07-15&months=6');

        $response->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.period', '2026-02')
            ->assertJsonPath('data.5.period', '2026-07');
    }

    public function test_defaults_to_six_months_when_months_is_omitted(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cash-flow/evolution?reference_date=2026-07-15');

        $response->assertOk()->assertJsonCount(6, 'data');
    }

    public function test_fails_when_months_is_out_of_allowed_range(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/cash-flow/evolution?months=25');

        $response->assertUnprocessable()->assertJsonValidationErrors(['months']);
    }
}
