<?php

namespace Tests\Feature\Services\OpenFinance;

use App\Exceptions\ServiceException;
use App\Services\OpenFinance\PluggyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PluggyClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_connect_token_returns_the_access_token(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/connect_token' => Http::response(['accessToken' => 'test-connect-token']),
        ]);

        $token = (new PluggyClient)->createConnectToken();

        $this->assertSame('test-connect-token', $token);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.pluggy.ai/connect_token'
            && ! array_key_exists('itemId', $request->data()));
    }

    public function test_the_api_key_is_cached_across_multiple_calls(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/connect_token' => Http::response(['accessToken' => 'test-connect-token']),
        ]);

        $client = new PluggyClient;
        $client->createConnectToken();
        $client->createConnectToken();

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.pluggy.ai/auth');
    }

    public function test_get_item_returns_the_decoded_payload(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/items/*' => Http::response(['id' => 'item-1', 'status' => 'UPDATED']),
        ]);

        $item = (new PluggyClient)->getItem('item-1');

        $this->assertSame('UPDATED', $item['status']);
    }

    public function test_delete_item_does_not_throw_when_the_item_no_longer_exists(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/items/*' => Http::response(['message' => 'not found'], 404),
        ]);

        (new PluggyClient)->deleteItem('item-1');

        $this->addToAssertionCount(1);
    }

    public function test_list_accounts_returns_the_results_array(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/accounts*' => Http::response(['results' => [['id' => 'acc-1']]]),
        ]);

        $accounts = (new PluggyClient)->listAccounts('item-1');

        $this->assertSame([['id' => 'acc-1']], $accounts);
    }

    public function test_list_all_transactions_paginates_until_the_last_page(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/transactions*' => function ($request) {
                $page = (int) ($request['page'] ?? 1);

                return Http::response([
                    'results' => [['id' => "tx-{$page}"]],
                    'page' => $page,
                    'totalPages' => 2,
                ]);
            },
        ]);

        $transactions = (new PluggyClient)->listAllTransactions('acc-1', null, null);

        $this->assertSame([['id' => 'tx-1'], ['id' => 'tx-2']], $transactions);
    }

    public function test_auth_failure_throws_a_service_exception(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['message' => 'invalid credentials'], 401),
        ]);

        $this->expectException(ServiceException::class);

        (new PluggyClient)->createConnectToken();
    }

    public function test_a_failed_response_throws_a_service_exception(): void
    {
        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/items/*' => Http::response(['message' => 'server error'], 500),
        ]);

        $this->expectException(ServiceException::class);

        (new PluggyClient)->getItem('item-1');
    }
}
