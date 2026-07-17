<?php

namespace Tests\Feature\Api\V1;

use App\Enum\AccountType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_accounts(): void
    {
        $this->getJson('/api/v1/accounts')->assertUnauthorized();
    }

    public function test_user_can_create_an_account(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/accounts', [
            'name' => 'Conta Corrente',
            'type' => AccountType::CHECKING->value,
            'initial_balance_cents' => 100000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Conta Corrente')
            ->assertJsonPath('initial_balance_cents', 100000)
            ->assertJsonPath('current_balance_cents', 100000);

        $this->assertDatabaseHas('accounts', ['name' => 'Conta Corrente']);
    }

    public function test_user_only_sees_their_own_accounts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Account::factory()->for($userA)->create(['name' => 'Conta A']);
        Account::factory()->for($userB)->create(['name' => 'Conta B']);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/accounts');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Conta A']);
        $response->assertJsonMissing(['name' => 'Conta B']);
    }

    public function test_user_cannot_view_another_users_account(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $account = Account::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/accounts/{$account->id}")->assertNotFound();
    }

    public function test_user_cannot_update_another_users_account(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $account = Account::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $this->putJson("/api/v1/accounts/{$account->id}", ['name' => 'Hackeado'])
            ->assertNotFound();

        $this->assertDatabaseMissing('accounts', ['id' => $account->id, 'name' => 'Hackeado']);
    }

    public function test_user_can_update_their_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'Antigo Nome']);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/accounts/{$account->id}", ['name' => 'Novo Nome'])
            ->assertOk()
            ->assertJsonPath('name', 'Novo Nome');
    }

    public function test_deleting_an_account_soft_deletes_it(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/accounts/{$account->id}")->assertNoContent();

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }
}
