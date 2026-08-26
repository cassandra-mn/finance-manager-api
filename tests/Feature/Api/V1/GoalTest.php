<?php

namespace Tests\Feature\Api\V1;

use App\Enum\AccountType;
use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_goal_linked_to_an_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::SAVINGS, 'initial_balance_cents' => 0]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/goals', [
            'account_id' => $account->id,
            'name' => 'Reserva de Emergência',
            'target_cents' => 1000000,
            'target_date' => '2027-01-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Reserva de Emergência')
            ->assertJsonPath('target_cents', 1000000)
            ->assertJsonPath('current_cents', 0)
            ->assertJsonPath('progress_percentage', 0)
            ->assertJsonPath('is_achieved', false)
            ->assertJsonPath('account.id', $account->id);

        $this->assertDatabaseHas('goals', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => 'Reserva de Emergência',
            'target_cents' => 1000000,
        ]);
    }

    public function test_defaults_color_and_icon_when_not_provided(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::SAVINGS]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/goals', [
            'account_id' => $account->id,
            'name' => 'Viagem',
            'target_cents' => 500000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('color', '#10b981')
            ->assertJsonPath('icon', 'PiggyBank');
    }

    public function test_progress_reflects_the_account_current_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::SAVINGS, 'initial_balance_cents' => 200000]);
        $goal = Goal::factory()->for($user)->for($account)->create(['target_cents' => 1000000]);

        Transaction::factory()->for($user)->for($account)->income()->paid()->create(['amount_cents' => 300000]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/goals/{$goal->id}");

        // 200000 (saldo inicial) + 300000 (receita paga) = 500000.
        $response->assertOk()
            ->assertJsonPath('current_cents', 500000)
            ->assertJsonPath('remaining_cents', 500000)
            ->assertJsonPath('progress_percentage', 50)
            ->assertJsonPath('is_achieved', false);
    }

    public function test_marks_as_achieved_once_the_balance_reaches_the_target(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::SAVINGS, 'initial_balance_cents' => 1500000]);
        $goal = Goal::factory()->for($user)->for($account)->create(['target_cents' => 1000000]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/goals/{$goal->id}");

        $response->assertOk()
            ->assertJsonPath('is_achieved', true)
            ->assertJsonPath('progress_percentage', 100)
            ->assertJsonPath('remaining_cents', 0);
    }

    public function test_fails_validation_with_missing_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/goals', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['account_id', 'name', 'target_cents']);
    }

    public function test_fails_with_another_users_account(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/v1/goals', [
            'account_id' => $accountB->id,
            'name' => 'Meta',
            'target_cents' => 100000,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['account_id']);
    }

    public function test_user_can_update_a_goal(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::SAVINGS]);
        $goal = Goal::factory()->for($user)->for($account)->create(['target_cents' => 500000, 'target_date' => '2026-12-31']);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/goals/{$goal->id}", [
            'target_cents' => 800000,
        ]);

        $response->assertOk()->assertJsonPath('target_cents', 800000);
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'target_cents' => 800000]);
    }

    public function test_user_can_clear_the_target_date(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::SAVINGS]);
        $goal = Goal::factory()->for($user)->for($account)->create(['target_date' => '2026-12-31']);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/goals/{$goal->id}", ['target_date' => null]);

        $response->assertOk()->assertJsonPath('target_date', null);
        $this->assertDatabaseHas('goals', ['id' => $goal->id, 'target_date' => null]);
    }

    public function test_user_can_delete_a_goal(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::SAVINGS]);
        $goal = Goal::factory()->for($user)->for($account)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/goals/{$goal->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
    }

    public function test_user_cannot_access_another_users_goal(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();
        $goal = Goal::factory()->for($userB)->for($accountB)->create();

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/goals/{$goal->id}")->assertNotFound();
        $this->patchJson("/api/v1/goals/{$goal->id}", ['target_cents' => 1000])->assertNotFound();
        $this->deleteJson("/api/v1/goals/{$goal->id}")->assertNotFound();
    }

    public function test_index_lists_only_the_users_own_goals_with_progress(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::SAVINGS, 'initial_balance_cents' => 100000]);
        Goal::factory()->for($user)->for($account)->create(['target_cents' => 200000]);

        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->for($otherUser)->create();
        Goal::factory()->for($otherUser)->for($otherAccount)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/goals');

        $response->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.current_cents', 100000);
    }

    public function test_guest_cannot_access_goals(): void
    {
        $this->getJson('/api/v1/goals')->assertUnauthorized();
        $this->postJson('/api/v1/goals', [])->assertUnauthorized();
    }
}
