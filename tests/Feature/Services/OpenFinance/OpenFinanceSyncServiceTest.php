<?php

namespace Tests\Feature\Services\OpenFinance;

use App\Enum\AccountType;
use App\Enum\BankConnectionStatus;
use App\Enum\TransactionOrigin;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\BankConnection;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OpenFinance\OpenFinanceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenFinanceSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = 'item-123';

    private const ACCOUNT_EXTERNAL_ID = 'ext-acc-1';

    public function test_sync_creates_the_account_and_imports_posted_transactions(): void
    {
        $connection = $this->createConnection();
        $this->fakePluggy();

        $summary = app(OpenFinanceSyncService::class)->sync($connection);

        $this->assertSame(1, $summary->accountsSynced);
        $this->assertSame(2, $summary->transactionsCreated);

        $account = Account::query()->where('bank_connection_id', $connection->id)->firstOrFail();
        $this->assertSame(AccountType::CHECKING, $account->type);
        $this->assertSame(self::ACCOUNT_EXTERNAL_ID, $account->external_account_id);

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'external_id' => 'tx-expense',
            'origin' => TransactionOrigin::OPEN_FINANCE->value,
            'type' => TransactionType::EXPENSE->value,
            'status' => TransactionStatus::PAID->value,
            'amount_cents' => 15050,
        ]);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'external_id' => 'tx-income',
            'type' => TransactionType::INCOME->value,
            'amount_cents' => 500000,
        ]);
        $this->assertDatabaseMissing('transactions', ['external_id' => 'tx-pending']);

        $connection->refresh();
        $this->assertSame(BankConnectionStatus::UPDATED, $connection->status);
        $this->assertNotNull($connection->last_synced_at);
        $this->assertNull($connection->last_sync_error);
    }

    public function test_resyncing_does_not_duplicate_previously_imported_transactions(): void
    {
        $connection = $this->createConnection();
        $this->fakePluggy();

        $service = app(OpenFinanceSyncService::class);
        $service->sync($connection);
        $countAfterFirstSync = Transaction::query()->count();

        $connection->refresh();
        $service->sync($connection);
        $countAfterSecondSync = Transaction::query()->count();

        $this->assertGreaterThan(0, $countAfterFirstSync);
        $this->assertSame($countAfterFirstSync, $countAfterSecondSync);
    }

    public function test_a_locally_deleted_account_is_not_recreated_on_resync(): void
    {
        $connection = $this->createConnection();
        $this->fakePluggy();

        app(OpenFinanceSyncService::class)->sync($connection);

        $account = Account::query()->where('bank_connection_id', $connection->id)->firstOrFail();
        $account->delete();

        $connection->refresh();
        $summary = app(OpenFinanceSyncService::class)->sync($connection);

        $this->assertSame(0, $summary->accountsSynced);
        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_waiting_user_input_status_stops_before_fetching_accounts(): void
    {
        $connection = $this->createConnection();

        Http::fake([
            'https://api.pluggy.ai/auth' => Http::response(['apiKey' => 'test-api-key']),
            'https://api.pluggy.ai/items/*' => Http::response(['id' => self::ITEM_ID, 'status' => 'WAITING_USER_INPUT']),
        ]);

        app(OpenFinanceSyncService::class)->sync($connection);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/accounts'));

        $connection->refresh();
        $this->assertSame(BankConnectionStatus::WAITING_USER_INPUT, $connection->status);
        $this->assertNotNull($connection->last_sync_error);
        $this->assertNull($connection->last_synced_at);
    }

    private function createConnection(): BankConnection
    {
        $user = User::factory()->create();

        return BankConnection::factory()->for($user)->create([
            'pluggy_item_id' => self::ITEM_ID,
            'status' => BankConnectionStatus::UPDATING,
            'institution_name' => null,
        ]);
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
                    'id' => self::ACCOUNT_EXTERNAL_ID,
                    'type' => 'BANK',
                    'subtype' => 'CHECKING_ACCOUNT',
                    'name' => 'Conta Corrente Pluggy',
                ]],
            ]),
            'https://api.pluggy.ai/transactions*' => Http::response([
                'results' => [
                    [
                        'id' => 'tx-expense',
                        'description' => 'Supermercado',
                        'amount' => 150.50,
                        'date' => '2026-07-20T00:00:00.000Z',
                        'type' => 'DEBIT',
                        'status' => 'POSTED',
                    ],
                    [
                        'id' => 'tx-income',
                        'description' => 'Salário',
                        'amount' => 5000.00,
                        'date' => '2026-07-25T00:00:00.000Z',
                        'type' => 'CREDIT',
                        'status' => 'POSTED',
                    ],
                    [
                        'id' => 'tx-pending',
                        'description' => 'Compra em processamento',
                        'amount' => 99.00,
                        'date' => '2026-07-28T00:00:00.000Z',
                        'type' => 'DEBIT',
                        'status' => 'PENDING',
                    ],
                ],
                'page' => 1,
                'totalPages' => 1,
            ]),
        ]);
    }
}
