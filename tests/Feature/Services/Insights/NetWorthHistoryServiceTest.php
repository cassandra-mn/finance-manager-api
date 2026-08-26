<?php

namespace Tests\Feature\Services\Insights;

use App\Enum\AccountType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\NetWorthHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NetWorthHistoryServiceTest extends TestCase
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

    public function test_returns_one_entry_per_month_with_a_flat_balance_when_nothing_changed(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 100000]);
        // Back-dated pra existir desde antes da janela de 3 meses — senão o
        // filtro de created_at (correto: uma conta não retroage saldo pra
        // antes de existir) excluiria ela dos meses passados.
        $account->forceFill(['created_at' => '2026-01-01'])->save();

        $result = app(NetWorthHistoryService::class)->history($user->id, Carbon::today(), 3);

        $this->assertCount(3, $result);
        $this->assertSame(['2026-06', '2026-07', '2026-08'], array_column($result, 'month'));
        $this->assertSame([100000, 100000, 100000], array_column($result, 'balance_cents'));
    }

    public function test_a_payment_only_raises_the_balance_from_its_month_onward(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 0]);
        $account->forceFill(['created_at' => '2026-01-01'])->save();

        Transaction::factory()->for($user)->for($account)
            ->income()
            ->create(['amount_cents' => 50000, 'status' => 'paid', 'paid_at' => '2026-07-10']);

        $result = app(NetWorthHistoryService::class)->history($user->id, Carbon::today(), 3);

        $june = collect($result)->firstWhere('month', '2026-06');
        $july = collect($result)->firstWhere('month', '2026-07');
        $august = collect($result)->firstWhere('month', '2026-08');

        $this->assertSame(0, $june['balance_cents']);
        $this->assertSame(50000, $july['balance_cents']);
        $this->assertSame(50000, $august['balance_cents']);
    }

    public function test_a_partial_payment_only_counts_the_amount_actually_paid(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 100000]);

        Transaction::factory()->for($user)->for($account)
            ->expense()
            ->partiallyPaid(3000)
            ->create(['amount_cents' => 20000, 'paid_at' => '2026-08-05']);

        $result = app(NetWorthHistoryService::class)->history($user->id, Carbon::today(), 1);

        // Só os 3000 efetivamente pagos saíram do saldo, não os 20000 de face.
        $this->assertSame(97000, $result[0]['balance_cents']);
    }

    public function test_excludes_credit_card_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->creditCard()->create(['initial_balance_cents' => 500000]);

        $result = app(NetWorthHistoryService::class)->history($user->id, Carbon::today(), 1);

        $this->assertSame(0, $result[0]['balance_cents']);
    }

    public function test_an_account_created_after_a_past_month_does_not_contribute_to_that_month(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 80000]);
        // created_at não é mass-assignable — força diretamente pra simular
        // uma conta cadastrada só em agosto.
        $account->forceFill(['created_at' => '2026-08-01'])->save();

        $result = app(NetWorthHistoryService::class)->history($user->id, Carbon::today(), 3);

        $june = collect($result)->firstWhere('month', '2026-06');
        $august = collect($result)->firstWhere('month', '2026-08');

        $this->assertSame(0, $june['balance_cents']);
        $this->assertSame(80000, $august['balance_cents']);
    }

    public function test_sums_across_multiple_non_credit_card_accounts(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 40000]);
        Account::factory()->for($user)->create(['type' => AccountType::SAVINGS, 'initial_balance_cents' => 60000]);

        $result = app(NetWorthHistoryService::class)->history($user->id, Carbon::today(), 1);

        $this->assertSame(100000, $result[0]['balance_cents']);
    }

    public function test_another_users_accounts_never_enter_the_history(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Account::factory()->for($userB)->create(['type' => AccountType::CHECKING, 'initial_balance_cents' => 999999]);

        $result = app(NetWorthHistoryService::class)->history($userA->id, Carbon::today(), 1);

        $this->assertSame(0, $result[0]['balance_cents']);
    }
}
