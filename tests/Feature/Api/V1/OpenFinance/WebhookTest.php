<?php

namespace Tests\Feature\Api\V1\OpenFinance;

use App\Jobs\OpenFinance\SyncBankConnectionJob;
use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('open_finance.pluggy.webhook_secret', 'test-secret');
    }

    public function test_a_valid_webhook_for_a_known_item_dispatches_a_sync(): void
    {
        Queue::fake();

        $connection = BankConnection::factory()->for(User::factory()->create())->create([
            'pluggy_item_id' => 'item-known',
        ]);

        $response = $this->postJson('/api/v1/open-finance/webhook?token=test-secret', [
            'event' => 'item/updated',
            'itemId' => 'item-known',
        ]);

        $response->assertOk();
        Queue::assertPushed(SyncBankConnectionJob::class, fn (SyncBankConnectionJob $job) => $job->bankConnectionId === $connection->id);
    }

    public function test_an_invalid_secret_is_rejected(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/open-finance/webhook?token=wrong-secret', [
            'event' => 'item/updated',
            'itemId' => 'item-known',
        ]);

        $response->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    public function test_a_missing_secret_is_rejected(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/open-finance/webhook', [
            'event' => 'item/updated',
            'itemId' => 'item-known',
        ]);

        $response->assertUnauthorized();
        Queue::assertNothingPushed();
    }

    public function test_an_unknown_item_id_is_acknowledged_without_dispatching_a_sync(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/open-finance/webhook?token=test-secret', [
            'event' => 'item/updated',
            'itemId' => 'item-does-not-exist',
        ]);

        $response->assertOk();
        Queue::assertNothingPushed();
    }
}
