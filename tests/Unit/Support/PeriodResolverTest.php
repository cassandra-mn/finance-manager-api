<?php

namespace Tests\Unit\Support;

use App\Enum\TransactionPeriod;
use App\Support\PeriodResolver;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PeriodResolverTest extends TestCase
{
    public function test_week_resolves_to_start_and_end_of_week(): void
    {
        $reference = Carbon::parse('2026-07-17'); // sexta-feira

        [$from, $to] = PeriodResolver::resolve(TransactionPeriod::WEEK, $reference);

        $this->assertSame('2026-07-13', $from->toDateString());
        $this->assertSame('2026-07-19', $to->toDateString());
    }

    public function test_month_resolves_to_start_and_end_of_month(): void
    {
        $reference = Carbon::parse('2026-07-17');

        [$from, $to] = PeriodResolver::resolve(TransactionPeriod::MONTH, $reference);

        $this->assertSame('2026-07-01', $from->toDateString());
        $this->assertSame('2026-07-31', $to->toDateString());
    }

    public function test_fortnight_resolves_to_first_half_when_day_is_15_or_less(): void
    {
        $reference = Carbon::parse('2026-07-10');

        [$from, $to] = PeriodResolver::resolve(TransactionPeriod::FORTNIGHT, $reference);

        $this->assertSame('2026-07-01', $from->toDateString());
        $this->assertSame('2026-07-15', $to->toDateString());
    }

    public function test_fortnight_resolves_to_second_half_when_day_is_after_15(): void
    {
        $reference = Carbon::parse('2026-07-20');

        [$from, $to] = PeriodResolver::resolve(TransactionPeriod::FORTNIGHT, $reference);

        $this->assertSame('2026-07-16', $from->toDateString());
        $this->assertSame('2026-07-31', $to->toDateString());
    }

    public function test_quarter_resolves_to_start_and_end_of_quarter(): void
    {
        $reference = Carbon::parse('2026-08-15');

        [$from, $to] = PeriodResolver::resolve(TransactionPeriod::QUARTER, $reference);

        $this->assertSame('2026-07-01', $from->toDateString());
        $this->assertSame('2026-09-30', $to->toDateString());
    }

    public function test_year_resolves_to_start_and_end_of_year(): void
    {
        $reference = Carbon::parse('2026-08-15');

        [$from, $to] = PeriodResolver::resolve(TransactionPeriod::YEAR, $reference);

        $this->assertSame('2026-01-01', $from->toDateString());
        $this->assertSame('2026-12-31', $to->toDateString());
    }

    public function test_previous_shifts_week_by_one_calendar_week(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::WEEK, Carbon::parse('2026-07-17'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::WEEK, $currentStart);

        $this->assertSame('2026-07-06', $from->toDateString());
        $this->assertSame('2026-07-12', $to->toDateString());
    }

    public function test_previous_shifts_month_by_one_calendar_month(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::MONTH, Carbon::parse('2026-08-15'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::MONTH, $currentStart);

        $this->assertSame('2026-07-01', $from->toDateString());
        $this->assertSame('2026-07-31', $to->toDateString());
    }

    public function test_previous_shifts_month_across_a_year_boundary(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::MONTH, Carbon::parse('2027-01-15'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::MONTH, $currentStart);

        $this->assertSame('2026-12-01', $from->toDateString());
        $this->assertSame('2026-12-31', $to->toDateString());
    }

    public function test_previous_shifts_quarter_across_a_year_boundary(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::QUARTER, Carbon::parse('2027-02-01'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::QUARTER, $currentStart);

        $this->assertSame('2026-10-01', $from->toDateString());
        $this->assertSame('2026-12-31', $to->toDateString());
    }

    public function test_previous_shifts_year(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::YEAR, Carbon::parse('2026-08-15'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::YEAR, $currentStart);

        $this->assertSame('2025-01-01', $from->toDateString());
        $this->assertSame('2025-12-31', $to->toDateString());
    }

    public function test_previous_fortnight_from_first_half_returns_second_half_of_prior_month(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::FORTNIGHT, Carbon::parse('2026-07-10'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::FORTNIGHT, $currentStart);

        $this->assertSame('2026-06-16', $from->toDateString());
        $this->assertSame('2026-06-30', $to->toDateString());
    }

    public function test_previous_fortnight_from_second_half_returns_first_half_of_same_month(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::FORTNIGHT, Carbon::parse('2026-07-20'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::FORTNIGHT, $currentStart);

        $this->assertSame('2026-07-01', $from->toDateString());
        $this->assertSame('2026-07-15', $to->toDateString());
    }

    public function test_previous_fortnight_handles_leap_february(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::FORTNIGHT, Carbon::parse('2024-03-10'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::FORTNIGHT, $currentStart);

        $this->assertSame('2024-02-16', $from->toDateString());
        $this->assertSame('2024-02-29', $to->toDateString());
    }

    public function test_previous_fortnight_handles_non_leap_february(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::FORTNIGHT, Carbon::parse('2026-03-10'));

        [$from, $to] = PeriodResolver::previous(TransactionPeriod::FORTNIGHT, $currentStart);

        $this->assertSame('2026-02-16', $from->toDateString());
        $this->assertSame('2026-02-28', $to->toDateString());
    }

    public function test_previous_year_resolves_the_same_month_one_year_earlier(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::MONTH, Carbon::parse('2026-08-15'));

        [$from, $to] = PeriodResolver::previousYear(TransactionPeriod::MONTH, $currentStart);

        $this->assertSame('2025-08-01', $from->toDateString());
        $this->assertSame('2025-08-31', $to->toDateString());
    }

    public function test_previous_year_is_not_the_same_as_previous_period_for_month(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::MONTH, Carbon::parse('2026-08-15'));

        [$previousPeriodFrom] = PeriodResolver::previous(TransactionPeriod::MONTH, $currentStart);
        [$previousYearFrom] = PeriodResolver::previousYear(TransactionPeriod::MONTH, $currentStart);

        $this->assertSame('2026-07-01', $previousPeriodFrom->toDateString());
        $this->assertSame('2025-08-01', $previousYearFrom->toDateString());
    }

    public function test_previous_year_resolves_the_same_quarter_one_year_earlier(): void
    {
        [$currentStart] = PeriodResolver::resolve(TransactionPeriod::QUARTER, Carbon::parse('2026-08-15'));

        [$from, $to] = PeriodResolver::previousYear(TransactionPeriod::QUARTER, $currentStart);

        $this->assertSame('2025-07-01', $from->toDateString());
        $this->assertSame('2025-09-30', $to->toDateString());
    }
}
