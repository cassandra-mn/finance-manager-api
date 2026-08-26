<?php

namespace Tests\Feature\Api\V1\Insights;

use App\Enum\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NetWorthHistoryTest extends TestCase
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

    public function test_guest_cannot_access_net_worth_history(): void
    {
        $this->getJson('/api/v1/insights/net-worth-history')->assertUnauthorized();
    }

    public function test_returns_a_six_month_history_by_default(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 200000]);
        // Back-dated pra existir desde antes da janela de 6 meses.
        $account->forceFill(['created_at' => '2026-01-01'])->save();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/net-worth-history');

        $response->assertOk()
            ->assertJsonPath('reference_period.lookback_months', 6)
            ->assertJsonPath('reference_period.from', '2026-03')
            ->assertJsonPath('reference_period.to', '2026-08')
            ->assertJsonPath('summary.starting_balance_cents', 200000)
            ->assertJsonPath('summary.ending_balance_cents', 200000)
            ->assertJsonPath('summary.change_cents', 0)
            ->assertJsonCount(6, 'data');
    }

    public function test_lookback_months_is_validated_between_1_and_24(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/net-worth-history?lookback_months=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lookback_months');

        $this->getJson('/api/v1/insights/net-worth-history?lookback_months=25')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lookback_months');
    }

    public function test_another_users_accounts_never_appear_in_the_history(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Account::factory()->for($userB)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 999999]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/insights/net-worth-history?lookback_months=1');

        $response->assertOk()->assertJsonPath('summary.ending_balance_cents', 0);
    }
}
