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

class AnomaliesTest extends TestCase
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

    public function test_guest_cannot_access_anomalies(): void
    {
        $this->getJson('/api/v1/insights/anomalies')->assertUnauthorized();
    }

    public function test_flags_a_category_with_no_historical_spend_as_new(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        Transaction::factory()->for($user)->for($account)->for($category)->expense()
            ->create(['amount_cents' => 4500, 'due_date' => '2026-08-05']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/anomalies');

        $response->assertOk()
            ->assertJsonPath('summary.new_categories_count', 1)
            ->assertJsonPath('data.0.is_new_category', true)
            ->assertJsonPath('data.0.is_anomalous', true);
    }

    public function test_lookback_periods_is_validated_between_1_and_12(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/anomalies?lookback_periods=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lookback_periods');

        $this->getJson('/api/v1/insights/anomalies?lookback_periods=13')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lookback_periods');
    }

    public function test_threshold_percentage_must_be_at_least_1(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/insights/anomalies?threshold_percentage=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('threshold_percentage');
    }

    public function test_another_users_categories_never_appear(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();
        $categoryB = Category::factory()->for($userB)->expense()->create();

        Transaction::factory()->for($userB)->for($accountB)->for($categoryB)->expense()
            ->create(['amount_cents' => 99999, 'due_date' => '2026-08-05']);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/insights/anomalies');

        $response->assertOk()->assertJsonPath('data', []);
    }
}
