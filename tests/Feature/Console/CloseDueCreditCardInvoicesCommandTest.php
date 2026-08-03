<?php

namespace Tests\Feature\Console;

use App\Enum\TransactionGroupStatus;
use App\Models\Account;
use App\Models\TransactionGroup;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseDueCreditCardInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_closes_open_invoices_whose_closing_date_has_passed(): void
    {
        $group = $this->createGroup(closingDate: '2026-07-25');

        $this->artisan('finance:close-due-credit-card-invoices', ['--date' => '2026-07-26'])
            ->assertExitCode(0);

        $this->assertSame(TransactionGroupStatus::CLOSED, $group->fresh()->status);
    }

    public function test_closes_an_invoice_whose_closing_date_is_exactly_the_reference_date(): void
    {
        $group = $this->createGroup(closingDate: '2026-07-25');

        $this->artisan('finance:close-due-credit-card-invoices', ['--date' => '2026-07-25'])
            ->assertExitCode(0);

        $this->assertSame(TransactionGroupStatus::CLOSED, $group->fresh()->status);
    }

    public function test_does_not_close_an_invoice_before_its_closing_date(): void
    {
        $group = $this->createGroup(closingDate: '2026-07-25');

        $this->artisan('finance:close-due-credit-card-invoices', ['--date' => '2026-07-20'])
            ->assertExitCode(0);

        $this->assertSame(TransactionGroupStatus::OPEN, $group->fresh()->status);
    }

    public function test_does_not_touch_invoices_that_are_already_closed_or_paid(): void
    {
        $closed = $this->createGroup(closingDate: '2026-07-25', status: TransactionGroupStatus::CLOSED);
        $paid = $this->createGroup(closingDate: '2026-07-25', status: TransactionGroupStatus::PAID);

        $this->artisan('finance:close-due-credit-card-invoices', ['--date' => '2026-07-26'])
            ->assertExitCode(0);

        $this->assertSame(TransactionGroupStatus::CLOSED, $closed->fresh()->status);
        $this->assertSame(TransactionGroupStatus::PAID, $paid->fresh()->status);
    }

    public function test_command_can_be_run_safely_more_than_once(): void
    {
        $this->createGroup(closingDate: '2026-07-25');

        $this->artisan('finance:close-due-credit-card-invoices', ['--date' => '2026-07-26'])->assertExitCode(0);
        $this->artisan('finance:close-due-credit-card-invoices', ['--date' => '2026-07-26'])->assertExitCode(0);
    }

    public function test_scheduler_registers_the_command_to_run_daily(): void
    {
        $this->artisan('inspire')->run();

        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'finance:close-due-credit-card-invoices'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    private function createGroup(string $closingDate, TransactionGroupStatus $status = TransactionGroupStatus::OPEN): TransactionGroup
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->creditCard()->create();

        return TransactionGroup::factory()->for($user)->for($account)->create([
            'closing_date' => $closingDate,
            'status' => $status,
        ]);
    }
}
