<?php

namespace Tests\Feature\Services\Insights;

use App\Enum\TransactionStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\AnnualReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sums_income_and_expense_per_month_and_totals_for_the_year(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        Transaction::factory()->for($user)->for($account)->income()
            ->create(['amount_cents' => 500000, 'due_date' => '2026-03-10']);
        Transaction::factory()->for($user)->for($account)->for($category)->expense()
            ->create(['amount_cents' => 200000, 'due_date' => '2026-03-15']);
        Transaction::factory()->for($user)->for($account)->income()
            ->create(['amount_cents' => 300000, 'due_date' => '2026-11-01']);

        $report = app(AnnualReportService::class)->build($user->id, 2026, 5);

        $this->assertSame(2026, $report['year']);
        $this->assertSame(800000, $report['summary']['total_income_cents']);
        $this->assertSame(200000, $report['summary']['total_expense_cents']);
        $this->assertSame(600000, $report['summary']['net_cents']);
        $this->assertSame(75.0, $report['summary']['savings_rate_percentage']);

        $this->assertCount(12, $report['monthly']);
        $march = collect($report['monthly'])->firstWhere('month', 3);
        $this->assertSame(500000, $march['income_cents']);
        $this->assertSame(200000, $march['expense_cents']);
        $this->assertSame(300000, $march['net_cents']);

        $january = collect($report['monthly'])->firstWhere('month', 1);
        $this->assertSame(0, $january['income_cents']);
        $this->assertSame(0, $january['expense_cents']);
    }

    public function test_transactions_outside_the_year_are_excluded(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($account)->income()
            ->create(['amount_cents' => 999999, 'due_date' => '2025-12-31']);
        Transaction::factory()->for($user)->for($account)->income()
            ->create(['amount_cents' => 999999, 'due_date' => '2027-01-01']);

        $report = app(AnnualReportService::class)->build($user->id, 2026, 5);

        $this->assertSame(0, $report['summary']['total_income_cents']);
    }

    public function test_cancelled_transactions_are_excluded(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($account)->expense()
            ->create(['status' => TransactionStatus::CANCELLED, 'amount_cents' => 99999, 'due_date' => '2026-05-10']);

        $report = app(AnnualReportService::class)->build($user->id, 2026, 5);

        $this->assertSame(0, $report['summary']['total_expense_cents']);
    }

    public function test_identifies_best_and_worst_month_by_net(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        // Fevereiro: saldo +400.
        Transaction::factory()->for($user)->for($account)->income()
            ->create(['amount_cents' => 40000, 'due_date' => '2026-02-05']);

        // Junho: saldo -300.
        Transaction::factory()->for($user)->for($account)->expense()
            ->create(['amount_cents' => 30000, 'due_date' => '2026-06-05']);

        $report = app(AnnualReportService::class)->build($user->id, 2026, 5);

        $this->assertSame(2, $report['best_month']['month']);
        $this->assertSame(40000, $report['best_month']['net_cents']);
        $this->assertSame(6, $report['worst_month']['month']);
        $this->assertSame(-30000, $report['worst_month']['net_cents']);
    }

    public function test_ranks_top_categories_by_expense_for_the_whole_year(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $categoryA = Category::factory()->for($user)->expense()->create(['name' => 'Alimentação']);
        $categoryB = Category::factory()->for($user)->expense()->create(['name' => 'Transporte']);

        Transaction::factory()->for($user)->for($account)->for($categoryA)->expense()
            ->create(['amount_cents' => 100000, 'due_date' => '2026-01-10']);
        Transaction::factory()->for($user)->for($account)->for($categoryA)->expense()
            ->create(['amount_cents' => 50000, 'due_date' => '2026-08-10']);
        Transaction::factory()->for($user)->for($account)->for($categoryB)->expense()
            ->create(['amount_cents' => 30000, 'due_date' => '2026-04-10']);

        $report = app(AnnualReportService::class)->build($user->id, 2026, 5);

        $this->assertSame('Alimentação', $report['top_categories'][0]['category_name']);
        $this->assertSame(150000, $report['top_categories'][0]['amount_cents']);
        $this->assertSame('Transporte', $report['top_categories'][1]['category_name']);
    }

    public function test_another_users_transactions_never_appear(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();

        Transaction::factory()->for($userB)->for($accountB)->income()
            ->create(['amount_cents' => 999999, 'due_date' => '2026-05-10']);

        $report = app(AnnualReportService::class)->build($userA->id, 2026, 5);

        $this->assertSame(0, $report['summary']['total_income_cents']);
    }
}
