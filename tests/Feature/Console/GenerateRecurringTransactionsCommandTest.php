<?php

namespace Tests\Feature\Console;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GenerateRecurringTransactionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_recurrence_generates_the_correct_occurrence(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::WEEKLY, '2026-07-23');

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-07-23',
        ]);
        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-07-30',
        ]);
    }

    public function test_fortnightly_recurrence_generates_the_correct_occurrence(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::FORTNIGHTLY, '2026-07-23');

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-07-23',
        ]);
        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-08-06',
        ]);
    }

    public function test_monthly_recurrence_generates_the_correct_occurrence(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::MONTHLY, '2026-07-05');

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-07-05',
        ]);
        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-08-05',
        ]);
    }

    public function test_yearly_recurrence_generates_the_correct_occurrence(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::YEARLY, '2026-07-23', endDate: null);

        Config::set('finance.recurrences.generation_days', 400);

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-07-23',
        ]);
        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2027-07-23',
        ]);
    }

    public function test_paused_recurrence_does_not_generate_a_transaction(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::MONTHLY, '2026-07-23');
        $recurrence->update(['is_active' => false]);

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('transactions', ['recurrence_id' => $recurrence->id]);
    }

    public function test_recurrence_does_not_generate_transactions_after_end_date(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::WEEKLY, '2026-07-01', endDate: '2026-07-10');

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-07-01',
        ]);
        $this->assertDatabaseHas('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-07-08',
        ]);
        $this->assertDatabaseMissing('transactions', [
            'recurrence_id' => $recurrence->id,
            'due_date' => '2026-07-15',
        ]);
    }

    public function test_soft_deleted_recurrence_does_not_generate_a_transaction(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::MONTHLY, '2026-07-23');
        $recurrence->delete();

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('transactions', ['recurrence_id' => $recurrence->id]);
    }

    public function test_generated_transaction_has_pending_status_and_inherits_the_rule_fields(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        $recurrence = Recurrence::factory()->for($user)->for($account)->for($category)->expense()->create([
            'entry_type' => TransactionEntryType::FIXED,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'notes' => 'Contrato residencial',
            'frequency' => RecurrenceFrequency::MONTHLY,
            'start_date' => '2026-07-23',
            'next_due_date' => '2026-07-23',
            'end_date' => null,
        ]);

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $transaction = Transaction::query()->where('recurrence_id', $recurrence->id)->where('due_date', '2026-07-23')->firstOrFail();

        $this->assertSame(TransactionStatus::PENDING, $transaction->status);
        $this->assertSame($account->id, $transaction->account_id);
        $this->assertSame($category->id, $transaction->category_id);
        $this->assertSame(TransactionType::EXPENSE, $transaction->type);
        $this->assertSame(TransactionEntryType::FIXED, $transaction->entry_type);
        $this->assertSame('Aluguel', $transaction->description);
        $this->assertSame('Contrato residencial', $transaction->notes);
        $this->assertSame(150000, $transaction->amount_cents);
        $this->assertSame($recurrence->id, $transaction->recurrence_id);
    }

    public function test_running_the_command_twice_does_not_duplicate_occurrences(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::MONTHLY, '2026-07-23');

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])->assertExitCode(0);
        $countAfterFirstRun = Transaction::query()->where('recurrence_id', $recurrence->id)->count();

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])->assertExitCode(0);
        $countAfterSecondRun = Transaction::query()->where('recurrence_id', $recurrence->id)->count();

        $this->assertGreaterThan(0, $countAfterFirstRun);
        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);
    }

    public function test_database_unique_constraint_prevents_duplicate_occurrences(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::MONTHLY, '2026-07-23');

        DB::table('transactions')->insert([
            'user_id' => $recurrence->user_id,
            'account_id' => $recurrence->account_id,
            'category_id' => null,
            'recurrence_id' => $recurrence->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'status' => TransactionStatus::PENDING->value,
            'description' => 'Ocorrência 1',
            'amount_cents' => 1000,
            'due_date' => '2026-07-23',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('transactions')->insert([
            'user_id' => $recurrence->user_id,
            'account_id' => $recurrence->account_id,
            'category_id' => null,
            'recurrence_id' => $recurrence->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'status' => TransactionStatus::PENDING->value,
            'description' => 'Ocorrência 1 duplicada',
            'amount_cents' => 1000,
            'due_date' => '2026-07-23',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_generation_respects_the_configured_window(): void
    {
        Config::set('finance.recurrences.generation_days', 10);

        $recurrence = $this->createRecurrence(RecurrenceFrequency::WEEKLY, '2026-07-23', endDate: null);

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', ['recurrence_id' => $recurrence->id, 'due_date' => '2026-07-23']);
        $this->assertDatabaseHas('transactions', ['recurrence_id' => $recurrence->id, 'due_date' => '2026-07-30']);
        $this->assertDatabaseMissing('transactions', ['recurrence_id' => $recurrence->id, 'due_date' => '2026-08-06']);
    }

    public function test_monthly_recurrence_on_day_31_behaves_correctly_in_shorter_months(): void
    {
        Config::set('finance.recurrences.generation_days', 120);

        $recurrence = $this->createRecurrence(RecurrenceFrequency::MONTHLY, '2026-01-31', endDate: null);

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-01-31'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', ['recurrence_id' => $recurrence->id, 'due_date' => '2026-01-31']);
        $this->assertDatabaseHas('transactions', ['recurrence_id' => $recurrence->id, 'due_date' => '2026-02-28']);
        $this->assertDatabaseHas('transactions', ['recurrence_id' => $recurrence->id, 'due_date' => '2026-03-31']);
    }

    public function test_yearly_recurrence_on_leap_date_behaves_correctly(): void
    {
        Config::set('finance.recurrences.generation_days', 800);

        $recurrence = $this->createRecurrence(RecurrenceFrequency::YEARLY, '2024-02-29', endDate: null);
        $recurrence->update(['next_due_date' => '2027-02-28']);

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2027-02-28'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('transactions', ['recurrence_id' => $recurrence->id, 'due_date' => '2027-02-28']);
        $this->assertDatabaseHas('transactions', ['recurrence_id' => $recurrence->id, 'due_date' => '2028-02-29']);
    }

    public function test_an_invalid_recurrence_does_not_block_processing_of_others(): void
    {
        $user = User::factory()->create();
        $invalidAccount = Account::factory()->for($user)->create();
        $validAccount = Account::factory()->for($user)->create();

        $invalidRecurrence = Recurrence::factory()->for($user)->for($invalidAccount)->create([
            'frequency' => RecurrenceFrequency::MONTHLY,
            'start_date' => '2026-07-23',
            'next_due_date' => '2026-07-23',
            'end_date' => null,
        ]);
        $invalidAccount->delete();

        $validRecurrence = Recurrence::factory()->for($user)->for($validAccount)->create([
            'frequency' => RecurrenceFrequency::MONTHLY,
            'start_date' => '2026-07-23',
            'next_due_date' => '2026-07-23',
            'end_date' => null,
        ]);

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('transactions', ['recurrence_id' => $invalidRecurrence->id]);
        $this->assertDatabaseHas('transactions', ['recurrence_id' => $validRecurrence->id, 'due_date' => '2026-07-23']);
    }

    public function test_next_due_date_is_updated_after_processing(): void
    {
        $recurrence = $this->createRecurrence(RecurrenceFrequency::MONTHLY, '2026-07-23');

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])
            ->assertExitCode(0);

        $recurrence->refresh();

        $this->assertSame('2026-09-23', $recurrence->next_due_date->toDateString());
    }

    public function test_command_can_be_run_safely_more_than_once(): void
    {
        $this->createRecurrence(RecurrenceFrequency::MONTHLY, '2026-07-23');

        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])->assertExitCode(0);
        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])->assertExitCode(0);
        $this->artisan('finance:generate-recurring-transactions', ['--date' => '2026-07-23'])->assertExitCode(0);
    }

    public function test_scheduler_registers_the_command_to_run_daily(): void
    {
        $this->artisan('inspire')->run();

        $schedule = $this->app->make(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains($event->command ?? '', 'finance:generate-recurring-transactions'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    private function createRecurrence(RecurrenceFrequency $frequency, string $startDate, ?string $endDate = null): Recurrence
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        return Recurrence::factory()->for($user)->for($account)->expense()->create([
            'category_id' => null,
            'frequency' => $frequency,
            'start_date' => $startDate,
            'next_due_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
