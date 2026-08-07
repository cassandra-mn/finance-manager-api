<?php

namespace Tests\Feature\Api\V1\Insights;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartialPaymentsTest extends TestCase
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

    public function test_guest_cannot_access_partial_payments(): void
    {
        $this->getJson('/api/v1/insights/partial-payments')->assertUnauthorized();
    }

    public function test_sums_the_shortfall_of_the_current_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($account)
            ->partiallyPaid(5000)
            ->create(['amount_cents' => 20000]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/partial-payments');

        $response->assertOk()
            ->assertJsonPath('summary.total_shortfall_cents', 15000)
            ->assertJsonPath('summary.total_count', 1);
    }

    public function test_lookback_months_is_validated_between_1_and_24(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/partial-payments?lookback_months=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lookback_months');

        $this->getJson('/api/v1/insights/partial-payments?lookback_months=25')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lookback_months');
    }

    public function test_another_users_partial_payments_never_appear(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();

        Transaction::factory()->for($userB)->for($accountB)
            ->partiallyPaid(5000)
            ->create(['amount_cents' => 20000]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/insights/partial-payments');

        $response->assertOk()->assertJsonPath('summary.total_shortfall_cents', 0);
    }
}
