<?php

namespace Tests\Feature\Api\V1\Insights;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnualReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_annual_report(): void
    {
        $this->getJson('/api/v1/insights/annual-report')->assertUnauthorized();
    }

    public function test_defaults_to_the_current_year_when_no_year_is_given(): void
    {
        Carbon::setTestNow('2026-08-15');
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/insights/annual-report');

        $response->assertOk()->assertJsonPath('year', 2026);

        Carbon::setTestNow();
    }

    public function test_returns_totals_and_monthly_breakdown_for_the_requested_year(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($account)->income()
            ->create(['amount_cents' => 500000, 'due_date' => '2025-06-05']);
        Transaction::factory()->for($user)->for($account)->expense()
            ->create(['amount_cents' => 200000, 'due_date' => '2025-06-10']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/annual-report?year=2025');

        $response->assertOk()
            ->assertJsonPath('year', 2025)
            ->assertJsonPath('summary.total_income_cents', 500000)
            ->assertJsonPath('summary.total_expense_cents', 200000)
            ->assertJsonPath('summary.net_cents', 300000)
            ->assertJsonCount(12, 'monthly');
    }

    public function test_year_must_be_within_a_valid_range(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/annual-report?year=1500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');
    }

    public function test_another_users_transactions_never_appear(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();

        Transaction::factory()->for($userB)->for($accountB)->income()
            ->create(['amount_cents' => 999999, 'due_date' => '2026-05-10']);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/insights/annual-report?year=2026');

        $response->assertOk()->assertJsonPath('summary.total_income_cents', 0);
    }
}
