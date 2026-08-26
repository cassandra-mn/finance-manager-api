<?php

namespace Tests\Feature\Services\Insights;

use App\Enum\AccountType;
use App\Enum\RecurrenceFrequency;
use App\Models\Account;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Services\Insights\CashFlowForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CashFlowForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_returns_one_entry_per_month_starting_from_the_current_balance(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 100000]);

        $result = app(CashFlowForecastService::class)->project($user->id, Carbon::today(), 3);

        $this->assertCount(3, $result);
        $this->assertSame(['2026-08', '2026-09', '2026-10'], array_column($result, 'month'));
        $this->assertSame(100000, $result[0]['projected_balance_cents']);
        $this->assertSame(0, $result[0]['net_cents']);
    }

    public function test_includes_pending_ungrouped_transactions_in_their_due_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 0]);

        Transaction::factory()->for($user)->for($account)
            ->expense()
            ->create(['amount_cents' => 5000, 'due_date' => '2026-09-10']);

        $result = app(CashFlowForecastService::class)->project($user->id, Carbon::today(), 2);

        $september = collect($result)->firstWhere('month', '2026-09');
        $this->assertSame(5000, $september['expense_cents']);
        $this->assertSame(0, $result[0]['expense_cents']);
    }

    public function test_excludes_transactions_grouped_in_an_invoice_to_avoid_double_counting(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->creditCard()->create(['initial_balance_cents' => 0]);

        $group = TransactionGroup::factory()->for($user)->for($account)
            ->create(['due_date' => '2026-09-10']);

        Transaction::factory()->for($user)->for($account)
            ->expense()
            ->create(['transaction_group_id' => $group->id, 'amount_cents' => 3000, 'due_date' => '2026-09-10']);

        $result = app(CashFlowForecastService::class)->project($user->id, Carbon::today(), 2);

        $september = collect($result)->firstWhere('month', '2026-09');
        // Só uma vez (pela fatura), não duas (fatura + transação avulsa).
        $this->assertSame(3000, $september['expense_cents']);
    }

    public function test_excludes_paid_invoices_from_the_forecast(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->creditCard()->create(['initial_balance_cents' => 0]);

        $group = TransactionGroup::factory()->for($user)->for($account)
            ->paid()
            ->create(['due_date' => '2026-09-10']);

        Transaction::factory()->for($user)->for($account)
            ->create(['transaction_group_id' => $group->id, 'amount_cents' => 3000, 'due_date' => '2026-09-10']);

        $result = app(CashFlowForecastService::class)->project($user->id, Carbon::today(), 2);

        $total = array_sum(array_column($result, 'expense_cents'));
        $this->assertSame(0, $total);
    }

    public function test_projects_future_recurrence_occurrences_beyond_the_generation_window(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 0]);

        Recurrence::factory()->for($user)->for($account)
            ->expense()
            ->create([
                'frequency' => RecurrenceFrequency::MONTHLY,
                'amount_cents' => 20000,
                'start_date' => '2026-09-01',
                'next_due_date' => '2026-09-01',
                'end_date' => null,
            ]);

        $result = app(CashFlowForecastService::class)->project($user->id, Carbon::today(), 3);

        $september = collect($result)->firstWhere('month', '2026-09');
        $october = collect($result)->firstWhere('month', '2026-10');
        $this->assertSame(20000, $september['expense_cents']);
        $this->assertSame(20000, $october['expense_cents']);
        $this->assertSame(0, $result[0]['expense_cents']);
    }

    public function test_stops_projecting_recurrence_occurrences_past_its_end_date(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 0]);

        Recurrence::factory()->for($user)->for($account)
            ->income()
            ->create([
                'frequency' => RecurrenceFrequency::MONTHLY,
                'amount_cents' => 15000,
                'start_date' => '2026-09-01',
                'next_due_date' => '2026-09-01',
                'end_date' => '2026-09-15',
            ]);

        $result = app(CashFlowForecastService::class)->project($user->id, Carbon::today(), 3);

        $september = collect($result)->firstWhere('month', '2026-09');
        $october = collect($result)->firstWhere('month', '2026-10');
        $this->assertSame(15000, $september['income_cents']);
        $this->assertSame(0, $october['income_cents']);
    }

    public function test_ignores_paused_recurrences(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 0]);

        Recurrence::factory()->for($user)->for($account)
            ->expense()
            ->paused()
            ->create([
                'frequency' => RecurrenceFrequency::MONTHLY,
                'amount_cents' => 20000,
                'start_date' => '2026-09-01',
                'next_due_date' => '2026-09-01',
            ]);

        $result = app(CashFlowForecastService::class)->project($user->id, Carbon::today(), 2);

        $this->assertSame(0, array_sum(array_column($result, 'expense_cents')));
    }

    public function test_chains_the_projected_balance_across_months(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)
            ->expense()
            ->create(['amount_cents' => 30000, 'due_date' => '2026-08-20']);

        Transaction::factory()->for($user)->for($account)
            ->income()
            ->create(['amount_cents' => 10000, 'due_date' => '2026-09-05']);

        $result = app(CashFlowForecastService::class)->project($user->id, Carbon::today(), 2);

        // Ago: 100000 - 30000 = 70000. Set: 70000 + 10000 = 80000.
        $this->assertSame(70000, $result[0]['projected_balance_cents']);
        $this->assertSame(80000, $result[1]['projected_balance_cents']);
    }

    public function test_another_users_data_never_enters_the_forecast(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 0]);

        Transaction::factory()->for($userB)->for($accountB)
            ->expense()
            ->create(['amount_cents' => 5000, 'due_date' => '2026-09-10']);

        $result = app(CashFlowForecastService::class)->project($userA->id, Carbon::today(), 2);

        $this->assertSame(0, array_sum(array_column($result, 'expense_cents')));
    }
}
