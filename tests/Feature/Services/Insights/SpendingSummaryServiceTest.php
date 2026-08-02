<?php

namespace Tests\Feature\Services\Insights;

use App\Enum\TransactionPeriod;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\SpendingSummaryService;
use App\Support\PeriodResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SpendingSummaryServiceTest extends TestCase
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

    public function test_compares_current_and_previous_month_totals_and_ranks_top_categories(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $food = Category::factory()->for($user)->expense()->create(['name' => 'Alimentação']);
        $transport = Category::factory()->for($user)->expense()->create(['name' => 'Transporte']);

        Transaction::factory()->for($user)->for($account)->for($food)->expense()
            ->create(['amount_cents' => 12000, 'due_date' => '2026-08-05']);
        Transaction::factory()->for($user)->for($account)->for($transport)->expense()
            ->create(['amount_cents' => 8000, 'due_date' => '2026-08-10']);
        Transaction::factory()->for($user)->for($account)->income()
            ->create(['category_id' => null, 'amount_cents' => 500000, 'due_date' => '2026-08-01']);

        Transaction::factory()->for($user)->for($account)->for($food)->expense()
            ->create(['amount_cents' => 10000, 'due_date' => '2026-07-05']);
        Transaction::factory()->for($user)->for($account)->income()
            ->create(['category_id' => null, 'amount_cents' => 480000, 'due_date' => '2026-07-01']);

        $result = $this->compare($user->id, 5);

        $this->assertSame(500000, $result['current']['income_cents']);
        $this->assertSame(20000, $result['current']['expense_cents']);
        $this->assertSame(480000, $result['previous']['income_cents']);
        $this->assertSame(10000, $result['previous']['expense_cents']);

        $this->assertSame(20000, $result['delta']['income_cents']);
        $this->assertSame(10000, $result['delta']['expense_cents']);
        $this->assertSame(4.17, $result['delta']['income_percentage']);
        $this->assertSame(100.0, $result['delta']['expense_percentage']);

        $this->assertSame('Alimentação', $result['top_categories'][0]['category_name']);
        $this->assertSame(12000, $result['top_categories'][0]['amount_cents']);
        $this->assertSame(60.0, $result['top_categories'][0]['percentage_of_total']);
        $this->assertSame('Transporte', $result['top_categories'][1]['category_name']);
        $this->assertSame(40.0, $result['top_categories'][1]['percentage_of_total']);
        $this->assertSame(0, $result['others_cents']);
    }

    public function test_categories_beyond_the_top_n_are_summed_into_others(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $food = Category::factory()->for($user)->expense()->create();
        $transport = Category::factory()->for($user)->expense()->create();

        Transaction::factory()->for($user)->for($account)->for($food)->expense()
            ->create(['amount_cents' => 12000, 'due_date' => '2026-08-05']);
        Transaction::factory()->for($user)->for($account)->for($transport)->expense()
            ->create(['amount_cents' => 8000, 'due_date' => '2026-08-10']);

        $result = $this->compare($user->id, 1);

        $this->assertCount(1, $result['top_categories']);
        $this->assertSame(8000, $result['others_cents']);
    }

    public function test_percentage_change_is_null_when_there_is_no_previous_data(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($account)->income()
            ->create(['category_id' => null, 'amount_cents' => 100000, 'due_date' => '2026-08-01']);

        $result = $this->compare($user->id, 5);

        $this->assertNull($result['delta']['income_percentage']);
        $this->assertNull($result['delta']['expense_percentage']);
    }

    private function compare(int $userId, int $topCategories): array
    {
        [$currentStart, $currentEnd] = PeriodResolver::resolve(TransactionPeriod::MONTH, Carbon::today());
        [$previousStart, $previousEnd] = PeriodResolver::previous(TransactionPeriod::MONTH, $currentStart);

        return app(SpendingSummaryService::class)->compare(
            $userId,
            $currentStart,
            $currentEnd,
            $previousStart,
            $previousEnd,
            $topCategories,
        );
    }
}
