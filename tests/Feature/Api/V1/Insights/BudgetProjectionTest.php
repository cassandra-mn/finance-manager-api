<?php

namespace Tests\Feature\Api\V1\Insights;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetProjectionTest extends TestCase
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

    public function test_guest_cannot_access_budget_projection(): void
    {
        $this->getJson('/api/v1/insights/budget-projection')->assertUnauthorized();
    }

    public function test_projects_spend_for_the_current_month(): void
    {
        [$user] = $this->seedBudget(8, 2026, amountCents: 80000, spentCents: 40000);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/budget-projection');

        $response->assertOk()
            ->assertJsonPath('reference_period.projection_applicable', true)
            ->assertJsonPath('reference_period.days_in_period', 31)
            ->assertJsonPath('reference_period.days_elapsed', 15)
            ->assertJsonPath('data.0.projected_spent_cents', 82666)
            ->assertJsonPath('data.0.is_projected_to_exceed', true);
    }

    public function test_projection_is_not_applicable_for_a_past_month(): void
    {
        [$user] = $this->seedBudget(7, 2026, amountCents: 80000, spentCents: 40000);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/budget-projection?reference_date=2026-07-15');

        $response->assertOk()
            ->assertJsonPath('reference_period.projection_applicable', false)
            ->assertJsonPath('data.0.projected_spent_cents', null)
            ->assertJsonPath('data.0.is_projected_to_exceed', null)
            ->assertJsonPath('summary.total_projected_spent_cents', null);
    }

    public function test_reference_date_must_be_a_valid_date(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/budget-projection?reference_date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference_date');
    }

    public function test_another_users_budgets_never_appear(): void
    {
        $userA = User::factory()->create();
        $this->seedBudget(8, 2026, amountCents: 50000, spentCents: 10000, forUser: User::factory()->create());

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/insights/budget-projection');

        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_does_not_crash_when_the_budgets_category_was_soft_deleted(): void
    {
        [$user, $budget] = $this->seedBudget(8, 2026, amountCents: 80000, spentCents: 40000);

        // Deleting a category does not currently block deletion when it
        // still has budgets attached (soft delete), which leaves this
        // budget's category relation resolving to null.
        $budget->category->delete();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/budget-projection');

        $response->assertOk()->assertJsonPath('data.0.category', null);
    }

    /** @return array{0: User, 1: Budget} */
    private function seedBudget(int $month, int $year, int $amountCents, int $spentCents, ?User $forUser = null): array
    {
        $user = $forUser ?? User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        $budget = Budget::factory()->for($user)->for($category)->forPeriod($month, $year)->create([
            'amount_cents' => $amountCents,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount_cents' => $spentCents,
            'due_date' => Carbon::create($year, $month, 10)->toDateString(),
        ]);

        return [$user, $budget];
    }
}
