<?php

namespace Tests\Feature\Api\V1\Insights;

use App\Enum\RecurrenceFrequency;
use App\Models\Account;
use App\Models\Recurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurringCommitmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_recurring_commitments(): void
    {
        $this->getJson('/api/v1/insights/recurring-commitments')->assertUnauthorized();
    }

    public function test_returns_the_list_and_summary_shape(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Recurrence::factory()->for($user)->for($account)
            ->expense()
            ->create(['description' => 'Streaming', 'frequency' => RecurrenceFrequency::MONTHLY, 'amount_cents' => 3990]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/insights/recurring-commitments');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Streaming')
            ->assertJsonPath('data.0.monthly_equivalent_cents', 3990)
            ->assertJsonPath('data.0.annual_cost_cents', 47880)
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('summary.total_monthly_expense_cents', 3990);
    }

    public function test_another_users_recurrences_never_appear(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();

        Recurrence::factory()->for($userB)->for($accountB)
            ->expense()
            ->create(['frequency' => RecurrenceFrequency::MONTHLY, 'amount_cents' => 99999]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/insights/recurring-commitments');

        $response->assertOk()->assertJsonPath('summary.count', 0);
    }
}
