<?php

namespace Tests\Feature\Services\Insights;

use App\Enum\RecurrenceFrequency;
use App\Models\Account;
use App\Models\Recurrence;
use App\Models\User;
use App\Services\Insights\RecurringCommitmentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringCommitmentsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_computes_monthly_and_annual_equivalent_per_frequency(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Recurrence::factory()->for($user)->for($account)->expense()->create(['frequency' => RecurrenceFrequency::WEEKLY, 'amount_cents' => 1200]);
        Recurrence::factory()->for($user)->for($account)->expense()->create(['frequency' => RecurrenceFrequency::FORTNIGHTLY, 'amount_cents' => 2000]);
        Recurrence::factory()->for($user)->for($account)->expense()->create(['frequency' => RecurrenceFrequency::MONTHLY, 'amount_cents' => 5000]);
        Recurrence::factory()->for($user)->for($account)->expense()->create(['frequency' => RecurrenceFrequency::YEARLY, 'amount_cents' => 24000]);

        $entries = app(RecurringCommitmentsService::class)->list($user->id);
        $byFrequency = collect($entries)->keyBy(fn (array $e) => $e['recurrence']->frequency->value);

        // Weekly R$12 -> annual 12*52=624 -> monthly 624/12=52.
        $this->assertSame(62400, $byFrequency['weekly']['annual_cost_cents']);
        $this->assertSame(5200, $byFrequency['weekly']['monthly_equivalent_cents']);

        // Fortnightly R$20 -> annual 20*26=520 -> monthly 520/12=43.33 (intdiv 43).
        $this->assertSame(52000, $byFrequency['fortnightly']['annual_cost_cents']);
        $this->assertSame(4333, $byFrequency['fortnightly']['monthly_equivalent_cents']);

        // Monthly R$50 -> annual 600, monthly 50 (unchanged).
        $this->assertSame(60000, $byFrequency['monthly']['annual_cost_cents']);
        $this->assertSame(5000, $byFrequency['monthly']['monthly_equivalent_cents']);

        // Yearly R$240 -> annual 240 (unchanged), monthly 20.
        $this->assertSame(24000, $byFrequency['yearly']['annual_cost_cents']);
        $this->assertSame(2000, $byFrequency['yearly']['monthly_equivalent_cents']);
    }

    public function test_ignores_paused_recurrences(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Recurrence::factory()->for($user)->for($account)->expense()->paused()->create(['frequency' => RecurrenceFrequency::MONTHLY, 'amount_cents' => 5000]);

        $entries = app(RecurringCommitmentsService::class)->list($user->id);

        $this->assertCount(0, $entries);
    }

    public function test_summarize_splits_income_and_expense_and_computes_net(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Recurrence::factory()->for($user)->for($account)->income()->create(['frequency' => RecurrenceFrequency::MONTHLY, 'amount_cents' => 900000]);
        Recurrence::factory()->for($user)->for($account)->expense()->create(['frequency' => RecurrenceFrequency::MONTHLY, 'amount_cents' => 5000]);
        Recurrence::factory()->for($user)->for($account)->expense()->create(['frequency' => RecurrenceFrequency::MONTHLY, 'amount_cents' => 3000]);

        $service = app(RecurringCommitmentsService::class);
        $entries = $service->list($user->id);
        $summary = $service->summarize($entries);

        $this->assertSame(900000, $summary['total_monthly_income_cents']);
        $this->assertSame(8000, $summary['total_monthly_expense_cents']);
        $this->assertSame(892000, $summary['net_monthly_cents']);
        $this->assertSame(3, $summary['count']);
    }

    public function test_another_users_recurrences_never_enter_the_audit(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();

        Recurrence::factory()->for($userB)->for($accountB)->expense()->create(['frequency' => RecurrenceFrequency::MONTHLY, 'amount_cents' => 99999]);

        $entries = app(RecurringCommitmentsService::class)->list($userA->id);

        $this->assertCount(0, $entries);
    }
}
