<?php

namespace Tests\Feature\Services\Insights;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Services\Insights\PartialPaymentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PartialPaymentsServiceTest extends TestCase
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

    public function test_sums_the_shortfall_of_partially_paid_transactions_and_invoices_by_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        // Conta de R$200 (20000), pagou R$50 (5000) — faltam 15000.
        Transaction::factory()->for($user)->for($account)
            ->partiallyPaid(5000)
            ->create(['amount_cents' => 20000]);

        // Fatura de R$300 (10000 + 20000), pagou R$120 (12000) — faltam 18000.
        $group = TransactionGroup::factory()->for($user)->for($account)
            ->partiallyPaid(12000)
            ->create();
        Transaction::factory()->for($user)->for($account)
            ->create(['transaction_group_id' => $group->id, 'amount_cents' => 10000]);
        Transaction::factory()->for($user)->for($account)
            ->create(['transaction_group_id' => $group->id, 'amount_cents' => 20000]);

        $result = app(PartialPaymentsService::class)->summarize($user->id, Carbon::today(), 6);

        $august = collect($result)->firstWhere('month', '2026-08');
        $this->assertNotNull($august);
        $this->assertSame(15000 + 18000, $august['shortfall_cents']);
        $this->assertSame(2, $august['count']);
        $this->assertStringContainsString('2026', $august['month_label']);
    }

    public function test_ignores_fully_paid_and_pending_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($account)->paid()->create(['amount_cents' => 20000]);
        Transaction::factory()->for($user)->for($account)->create(['amount_cents' => 20000]);

        $result = app(PartialPaymentsService::class)->summarize($user->id, Carbon::today(), 6);

        $total = array_sum(array_column($result, 'shortfall_cents'));
        $this->assertSame(0, $total);
    }

    public function test_only_counts_partial_payments_within_the_lookback_window(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        // Pago parcialmente há 8 meses — fora da janela padrão de 6 meses.
        Transaction::factory()->for($user)->for($account)
            ->partiallyPaid(1000)
            ->create(['amount_cents' => 5000, 'paid_at' => Carbon::parse('2025-12-10')]);

        $result = app(PartialPaymentsService::class)->summarize($user->id, Carbon::today(), 6);

        $total = array_sum(array_column($result, 'shortfall_cents'));
        $this->assertSame(0, $total);
    }

    public function test_returns_one_entry_per_month_in_the_lookback_window_even_without_data(): void
    {
        $user = User::factory()->create();

        $result = app(PartialPaymentsService::class)->summarize($user->id, Carbon::today(), 6);

        $this->assertCount(6, $result);
        $this->assertSame('2026-03', $result[0]['month']);
        $this->assertSame('2026-08', $result[5]['month']);
        $this->assertSame(0, $result[0]['shortfall_cents']);
    }
}
