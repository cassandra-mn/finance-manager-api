<?php

namespace Tests\Feature\Console;

use App\Enum\BankConnectionStatus;
use App\Jobs\OpenFinance\SyncBankConnectionJob;
use App\Models\BankConnection;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncOpenFinanceConnectionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_syncable_connections_receive_a_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $updated = BankConnection::factory()->for($user)->create(['status' => BankConnectionStatus::UPDATED]);
        $updating = BankConnection::factory()->for($user)->create(['status' => BankConnectionStatus::UPDATING]);
        $outdated = BankConnection::factory()->for($user)->create(['status' => BankConnectionStatus::OUTDATED]);
        $error = BankConnection::factory()->for($user)->create(['status' => BankConnectionStatus::ERROR]);
        BankConnection::factory()->for($user)->create(['status' => BankConnectionStatus::LOGIN_ERROR]);
        BankConnection::factory()->for($user)->create(['status' => BankConnectionStatus::WAITING_USER_INPUT]);
        $deleted = BankConnection::factory()->for($user)->create(['status' => BankConnectionStatus::UPDATED]);
        $deleted->delete();

        $this->artisan('open-finance:sync-connections')->assertExitCode(0);

        Queue::assertPushed(SyncBankConnectionJob::class, 4);
        foreach ([$updated, $updating, $outdated, $error] as $connection) {
            Queue::assertPushed(SyncBankConnectionJob::class, fn (SyncBankConnectionJob $job) => $job->bankConnectionId === $connection->id);
        }
    }

    public function test_connections_from_every_user_are_processed(): void
    {
        Queue::fake();

        BankConnection::factory()->for(User::factory()->create())->create(['status' => BankConnectionStatus::UPDATED]);
        BankConnection::factory()->for(User::factory()->create())->create(['status' => BankConnectionStatus::UPDATED]);

        $this->artisan('open-finance:sync-connections')->assertExitCode(0);

        Queue::assertPushed(SyncBankConnectionJob::class, 2);
    }

    public function test_scheduler_registers_the_command(): void
    {
        $this->artisan('inspire')->run();

        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'open-finance:sync-connections'));

        $this->assertNotNull($event);
    }
}
