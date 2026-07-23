<?php

namespace Tests\Feature\Api\V1;

use App\Enum\TransactionStatus;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_budget_for_an_expense_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->create(['name' => 'Alimentação']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'amount_cents' => 80000,
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);

        $response->assertCreated()
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('amount_cents', 80000)
            ->assertJsonPath('reference_month', 8)
            ->assertJsonPath('reference_year', 2026);

        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount_cents' => 80000,
        ]);
    }

    public function test_fails_to_create_budget_for_income_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->income()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'amount_cents' => 80000,
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    public function test_fails_with_another_users_category(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $categoryFromB = Category::factory()->for($userB)->expense()->create();

        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/v1/budgets', [
            'category_id' => $categoryFromB->id,
            'amount_cents' => 80000,
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    public function test_fails_to_create_duplicate_budget_for_same_category_and_period(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'amount_cents' => 50000,
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    public function test_soft_deleted_budget_does_not_block_creating_an_equivalent_one(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->create();
        $oldBudget = Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create();
        $oldBudget->delete();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'amount_cents' => 50000,
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);

        $response->assertCreated();
    }

    public function test_fails_when_amount_cents_is_zero_or_negative(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/budgets', [
            'category_id' => $category->id,
            'amount_cents' => 0,
            'reference_month' => 8,
            'reference_year' => 2026,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['amount_cents']);
    }

    public function test_user_only_sees_their_own_budgets(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Budget::factory()->for($userA)->create();
        Budget::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/budgets');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_can_filter_budgets_by_reference_date(): void
    {
        $user = User::factory()->create();
        Budget::factory()->for($user)->forPeriod(8, 2026)->create();
        Budget::factory()->for($user)->forPeriod(9, 2026)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets?reference_date=2026-08-15');

        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['reference_month' => 8]);
    }

    public function test_user_can_update_a_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create(['amount_cents' => 50000]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/budgets/{$budget->id}", [
            'amount_cents' => 99900,
        ]);

        $response->assertOk()->assertJsonPath('amount_cents', 99900);
    }

    public function test_user_can_soft_delete_a_budget(): void
    {
        $user = User::factory()->create();
        $budget = Budget::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/budgets/{$budget->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
        $this->getJson("/api/v1/budgets/{$budget->id}")->assertNotFound();
    }

    public function test_user_cannot_access_another_users_budget(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $budget = Budget::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/budgets/{$budget->id}")->assertNotFound();
        $this->patchJson("/api/v1/budgets/{$budget->id}", ['amount_cents' => 1000])->assertNotFound();
        $this->deleteJson("/api/v1/budgets/{$budget->id}")->assertNotFound();
    }

    public function test_status_calculates_spent_cents_from_paid_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()->paid()
            ->create(['amount_cents' => 30000, 'due_date' => '2026-08-10']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()->assertJsonPath('data.0.spent_cents', 30000);
    }

    public function test_status_calculates_spent_cents_from_pending_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()
            ->create(['status' => TransactionStatus::PENDING, 'amount_cents' => 20000, 'due_date' => '2026-08-20']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()->assertJsonPath('data.0.spent_cents', 20000);
    }

    public function test_status_excludes_cancelled_transactions_from_spent_cents(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()
            ->create(['status' => TransactionStatus::PAID, 'amount_cents' => 10000, 'due_date' => '2026-08-05']);
        Transaction::factory()->for($user)->for($account)->for($category)->expense()
            ->create(['status' => TransactionStatus::CANCELLED, 'amount_cents' => 50000, 'due_date' => '2026-08-06']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()->assertJsonPath('data.0.spent_cents', 10000);
    }

    public function test_status_excludes_transactions_from_another_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()->paid()
            ->create(['amount_cents' => 10000, 'due_date' => '2026-08-05']);
        Transaction::factory()->for($user)->for($account)->for($category)->expense()->paid()
            ->create(['amount_cents' => 99999, 'due_date' => '2026-09-01']);
        Transaction::factory()->for($user)->for($account)->for($category)->expense()->paid()
            ->create(['amount_cents' => 99999, 'due_date' => '2026-07-31']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()->assertJsonPath('data.0.spent_cents', 10000);
    }

    public function test_status_is_safe_below_80_percent(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()->paid()
            ->create(['amount_cents' => 79999, 'due_date' => '2026-08-10']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()->assertJsonPath('data.0.status', 'safe');
    }

    public function test_status_is_warning_at_exactly_80_percent(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()->paid()
            ->create(['amount_cents' => 80000, 'due_date' => '2026-08-10']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()
            ->assertJsonPath('data.0.status', 'warning')
            ->assertJsonPath('data.0.usage_percentage', 80);
    }

    public function test_status_is_warning_at_exactly_100_percent(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()->paid()
            ->create(['amount_cents' => 100000, 'due_date' => '2026-08-10']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()->assertJsonPath('data.0.status', 'warning');
    }

    public function test_status_is_exceeded_above_100_percent(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()->paid()
            ->create(['amount_cents' => 100001, 'due_date' => '2026-08-10']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()->assertJsonPath('data.0.status', 'exceeded');
    }

    public function test_status_returns_consolidated_summary(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $categoryA = Category::factory()->for($user)->expense()->create(['name' => 'Alimentação']);
        $categoryB = Category::factory()->for($user)->expense()->create(['name' => 'Transporte']);

        Budget::factory()->for($user)->for($categoryA)->forPeriod(8, 2026)->create(['amount_cents' => 100000]);
        Budget::factory()->for($user)->for($categoryB)->forPeriod(8, 2026)->create(['amount_cents' => 50000]);

        Transaction::factory()->for($user)->for($account)->for($categoryA)->expense()->paid()
            ->create(['amount_cents' => 50000, 'due_date' => '2026-08-10']);
        Transaction::factory()->for($user)->for($account)->for($categoryB)->expense()->paid()
            ->create(['amount_cents' => 62000, 'due_date' => '2026-08-10']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()
            ->assertJsonPath('summary.total_budget_cents', 150000)
            ->assertJsonPath('summary.total_spent_cents', 112000)
            ->assertJsonPath('summary.total_remaining_cents', 38000)
            ->assertJsonPath('summary.safe_count', 1)
            ->assertJsonPath('summary.exceeded_count', 1)
            ->assertJsonPath('summary.warning_count', 0);
    }

    public function test_status_uses_reference_date_to_resolve_month_and_year(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->expense()->create();
        Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/status?reference_date=2026-08-15');

        $response->assertOk()
            ->assertJsonPath('reference_period.month', 8)
            ->assertJsonPath('reference_period.year', 2026)
            ->assertJsonPath('reference_period.from', '2026-08-01')
            ->assertJsonPath('reference_period.to', '2026-08-31')
            ->assertJsonCount(1, 'data');
    }
}
