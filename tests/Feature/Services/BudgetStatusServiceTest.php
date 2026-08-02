<?php

namespace Tests\Feature\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_extrapolates_linearly_from_the_current_pace(): void
    {
        [$user, $budget] = $this->seedBudget(amountCents: 80000, spentCents: 40000);

        $entries = app(BudgetStatusService::class)->calculateWithProjection(
            $user->id,
            Budget::query()->where('id', $budget->id)->get(),
            8,
            2026,
            daysElapsed: 15,
            daysInPeriod: 31,
        );

        $this->assertSame(82666, $entries[0]['projected_spent_cents']);
        $this->assertSame(2666, $entries[0]['projected_overrun_cents']);
        $this->assertTrue($entries[0]['is_projected_to_exceed']);
    }

    public function test_projection_does_not_flag_exceed_when_projected_spend_stays_under_budget(): void
    {
        [$user, $budget] = $this->seedBudget(amountCents: 80000, spentCents: 10000);

        $entries = app(BudgetStatusService::class)->calculateWithProjection(
            $user->id,
            Budget::query()->where('id', $budget->id)->get(),
            8,
            2026,
            daysElapsed: 15,
            daysInPeriod: 31,
        );

        $this->assertSame(20666, $entries[0]['projected_spent_cents']);
        $this->assertSame(0, $entries[0]['projected_overrun_cents']);
        $this->assertFalse($entries[0]['is_projected_to_exceed']);
    }

    public function test_projection_collapses_to_actual_spend_when_days_elapsed_equals_days_in_period(): void
    {
        [$user, $budget] = $this->seedBudget(amountCents: 80000, spentCents: 45000);

        $entries = app(BudgetStatusService::class)->calculateWithProjection(
            $user->id,
            Budget::query()->where('id', $budget->id)->get(),
            8,
            2026,
            daysElapsed: 31,
            daysInPeriod: 31,
        );

        $this->assertSame(45000, $entries[0]['projected_spent_cents']);
    }

    /** @return array{0: User, 1: Budget} */
    private function seedBudget(int $amountCents, int $spentCents): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        $budget = Budget::factory()->for($user)->for($category)->forPeriod(8, 2026)->create([
            'amount_cents' => $amountCents,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => 'expense',
            'amount_cents' => $spentCents,
            'due_date' => '2026-08-10',
        ]);

        return [$user, $budget];
    }
}
