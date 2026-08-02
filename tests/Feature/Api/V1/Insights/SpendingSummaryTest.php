<?php

namespace Tests\Feature\Api\V1\Insights;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpendingSummaryTest extends TestCase
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

    public function test_guest_cannot_access_spending_summary(): void
    {
        $this->getJson('/api/v1/insights/spending-summary')->assertUnauthorized();
    }

    public function test_defaults_to_the_current_month_when_no_params_are_given(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/insights/spending-summary');

        $response->assertOk()
            ->assertJsonPath('reference_period.period', 'month')
            ->assertJsonPath('reference_period.current.from', '2026-08-01')
            ->assertJsonPath('reference_period.current.to', '2026-08-31')
            ->assertJsonPath('reference_period.previous.from', '2026-07-01')
            ->assertJsonPath('reference_period.previous.to', '2026-07-31');
    }

    public function test_returns_totals_and_top_categories(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create(['name' => 'Alimentação']);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()
            ->create(['amount_cents' => 15000, 'due_date' => '2026-08-05']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/spending-summary');

        $response->assertOk()
            ->assertJsonPath('data.current.expense_cents', 15000)
            ->assertJsonPath('data.top_categories.0.category_name', 'Alimentação')
            ->assertJsonPath('summary.total_expense_cents', 15000);
    }

    public function test_top_categories_is_validated_between_1_and_20(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/spending-summary?top_categories=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('top_categories');

        $this->getJson('/api/v1/insights/spending-summary?top_categories=21')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('top_categories');
    }

    public function test_period_must_be_a_valid_enum_value(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/spending-summary?period=decade')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period');
    }

    public function test_another_users_transactions_never_appear(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();
        $categoryB = Category::factory()->for($userB)->expense()->create();

        Transaction::factory()->for($userB)->for($accountB)->for($categoryB)->expense()
            ->create(['amount_cents' => 99999, 'due_date' => '2026-08-05']);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/insights/spending-summary');

        $response->assertOk()
            ->assertJsonPath('data.current.expense_cents', 0)
            ->assertJsonPath('data.top_categories', []);
    }
}
