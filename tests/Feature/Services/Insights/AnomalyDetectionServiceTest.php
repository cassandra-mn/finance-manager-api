<?php

namespace Tests\Feature\Services\Insights;

use App\Enum\TransactionPeriod;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Insights\AnomalyDetectionService;
use App\Support\PeriodResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnomalyDetectionServiceTest extends TestCase
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

    public function test_flags_a_category_that_exceeds_the_threshold_above_its_historical_average(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $food = Category::factory()->for($user)->expense()->create();

        $this->spendInMonths($user->id, $account->id, $food->id, ['2026-05', '2026-06', '2026-07'], 10000);
        $this->spend($user->id, $account->id, $food->id, 15000, '2026-08-05');

        $entries = $this->detect($user->id);
        $entry = collect($entries)->firstWhere('category_id', $food->id);

        $this->assertSame(15000, $entry['current_cents']);
        $this->assertSame(10000, $entry['average_cents']);
        $this->assertTrue($entry['is_anomalous']);
        $this->assertFalse($entry['is_new_category']);
    }

    public function test_does_not_flag_a_category_within_the_threshold(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transport = Category::factory()->for($user)->expense()->create();

        $this->spendInMonths($user->id, $account->id, $transport->id, ['2026-05', '2026-06', '2026-07'], 10000);
        $this->spend($user->id, $account->id, $transport->id, 11000, '2026-08-05');

        $entries = $this->detect($user->id);
        $entry = collect($entries)->firstWhere('category_id', $transport->id);

        $this->assertFalse($entry['is_anomalous']);
    }

    public function test_exactly_at_the_threshold_is_not_anomalous(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        $this->spendInMonths($user->id, $account->id, $category->id, ['2026-05', '2026-06', '2026-07'], 10000);
        // 40% acima de 10000 = 14000, exatamente no limiar.
        $this->spend($user->id, $account->id, $category->id, 14000, '2026-08-05');

        $entries = $this->detect($user->id, thresholdPercentage: 40);
        $entry = collect($entries)->firstWhere('category_id', $category->id);

        $this->assertFalse($entry['is_anomalous']);
    }

    public function test_one_cent_above_the_threshold_is_anomalous(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        $this->spendInMonths($user->id, $account->id, $category->id, ['2026-05', '2026-06', '2026-07'], 10000);
        $this->spend($user->id, $account->id, $category->id, 14001, '2026-08-05');

        $entries = $this->detect($user->id, thresholdPercentage: 40);
        $entry = collect($entries)->firstWhere('category_id', $category->id);

        $this->assertTrue($entry['is_anomalous']);
    }

    public function test_a_category_with_no_historical_spend_is_flagged_as_new(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $subscription = Category::factory()->for($user)->expense()->create();

        $this->spend($user->id, $account->id, $subscription->id, 4500, '2026-08-05');

        $entries = $this->detect($user->id);
        $entry = collect($entries)->firstWhere('category_id', $subscription->id);

        $this->assertSame(0, $entry['average_cents']);
        $this->assertNull($entry['deviation_percentage']);
        $this->assertTrue($entry['is_new_category']);
        $this->assertTrue($entry['is_anomalous']);
    }

    /** @return array<int, array<string, mixed>> */
    private function detect(int $userId, int $lookbackPeriods = 3, int $thresholdPercentage = 40): array
    {
        [$currentStart, $currentEnd] = PeriodResolver::resolve(TransactionPeriod::MONTH, Carbon::today());

        return app(AnomalyDetectionService::class)->detect(
            $userId,
            TransactionPeriod::MONTH,
            $currentStart,
            $currentEnd,
            $lookbackPeriods,
            $thresholdPercentage,
        );
    }

    private function spend(int $userId, int $accountId, int $categoryId, int $amountCents, string $dueDate): void
    {
        Transaction::factory()->create([
            'user_id' => $userId,
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'type' => 'expense',
            'amount_cents' => $amountCents,
            'due_date' => $dueDate,
        ]);
    }

    /** @param  list<string>  $yearMonths */
    private function spendInMonths(int $userId, int $accountId, int $categoryId, array $yearMonths, int $amountCents): void
    {
        foreach ($yearMonths as $yearMonth) {
            $this->spend($userId, $accountId, $categoryId, $amountCents, "{$yearMonth}-05");
        }
    }
}
