<?php

namespace Tests\Feature\Api\V1\OpenFinance;

use App\Enum\BankConnectionStatus;
use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankConnectionsTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = 'item-abc';

    public function test_guest_cannot_access_bank_connections(): void
    {
        $this->getJson('/api/v1/open-finance/connections')->assertUnauthorized();
    }

    public function test_user_can_request_a_connect_token(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/connect_token' => Http::response(['accessToken' => 'test-connect-token']),
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/open-finance/connect-token');

        $response->assertOk()->assertJsonPath('access_token', 'test-connect-token');
    }

    public function test_connecting_a_bank_creates_the_connection_and_imports_data(): void
    {
        $this->fakePluggy();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/open-finance/connections', ['item_id' => self::ITEM_ID]);

        $response->assertCreated()
            ->assertJsonPath('pluggy_item_id', self::ITEM_ID)
            ->assertJsonPath('status', BankConnectionStatus::UPDATED->value);

        $this->assertDatabaseHas('bank_connections', ['pluggy_item_id' => self::ITEM_ID]);
        $this->assertDatabaseHas('accounts', ['external_account_id' => 'ext-acc-1']);
        $this->assertDatabaseHas('transactions', ['external_id' => 'tx-1', 'amount_cents' => 15050]);
    }

    public function test_connecting_the_same_item_twice_fails_validation(): void
    {
        $this->fakePluggy();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/open-finance/connections', ['item_id' => self::ITEM_ID])->assertCreated();

        $response = $this->postJson('/api/v1/open-finance/connections', ['item_id' => self::ITEM_ID]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['item_id']);
    }

    public function test_user_only_sees_their_own_connections(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        BankConnection::factory()->for($userA)->create();
        BankConnection::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/open-finance/connections');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_user_cannot_view_another_users_connection(): void
    {
        $connection = BankConnection::factory()->for(User::factory()->create())->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/open-finance/connections/{$connection->id}")->assertNotFound();
    }

    public function test_resync_dispatches_a_new_sync(): void
    {
        $this->fakePluggy();

        $user = User::factory()->create();
        $connection = BankConnection::factory()->for($user)->create([
            'pluggy_item_id' => self::ITEM_ID,
            'last_synced_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/open-finance/connections/{$connection->id}/resync");

        $response->assertOk();
        $connection->refresh();
        $this->assertNotNull($connection->last_synced_at);
    }

    public function test_disconnecting_soft_deletes_the_connection_and_keeps_imported_data(): void
    {
        $this->fakePluggy();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/open-finance/connections', ['item_id' => self::ITEM_ID])->assertCreated();
        $connection = BankConnection::query()->where('pluggy_item_id', self::ITEM_ID)->firstOrFail();

        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/items/*' => Http::response([], 200),
        ]);

        $response = $this->deleteJson("/api/v1/open-finance/connections/{$connection->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('bank_connections', ['id' => $connection->id]);
        $this->assertDatabaseHas('accounts', ['bank_connection_id' => $connection->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('transactions', ['external_id' => 'tx-1', 'deleted_at' => null]);

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/items/'));
    }

    private function fakePluggy(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/items/*' => Http::response([
                'id' => self::ITEM_ID,
                'status' => 'UPDATED',
                'connector' => ['id' => '201', 'name' => 'Banco Teste'],
            ]),
            'https://api.pluggy.ai/accounts*' => Http::response([
                'results' => [[
                    'id' => 'ext-acc-1',
                    'type' => 'BANK',
                    'subtype' => 'CHECKING_ACCOUNT',
                    'name' => 'Conta Corrente Pluggy',
                ]],
            ]),
            'https://api.pluggy.ai/transactions*' => Http::response([
                'results' => [[
                    'id' => 'tx-1',
                    'description' => 'Supermercado',
                    'amount' => 150.50,
                    'date' => '2026-07-20T00:00:00.000Z',
                    'type' => 'DEBIT',
                    'status' => 'POSTED',
                ]],
                'page' => 1,
                'totalPages' => 1,
            ]),
        ]);
    }
}
