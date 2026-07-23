<?php

namespace Tests\Unit\Support;

use App\Enum\RecurrenceFrequency;
use App\Support\RecurrenceDateResolver;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class RecurrenceDateResolverTest extends TestCase
{
    public function test_weekly_advances_seven_days(): void
    {
        $current = Carbon::parse('2026-08-05');

        $next = RecurrenceDateResolver::next(RecurrenceFrequency::WEEKLY, $current, $current);

        $this->assertSame('2026-08-12', $next->toDateString());
    }

    public function test_fortnightly_advances_fourteen_days(): void
    {
        $current = Carbon::parse('2026-08-05');

        $next = RecurrenceDateResolver::next(RecurrenceFrequency::FORTNIGHTLY, $current, $current);

        $this->assertSame('2026-08-19', $next->toDateString());
    }

    public function test_monthly_advances_respecting_the_calendar(): void
    {
        $anchor = Carbon::parse('2026-08-05');
        $current = Carbon::parse('2026-08-05');

        $next = RecurrenceDateResolver::next(RecurrenceFrequency::MONTHLY, $anchor, $current);

        $this->assertSame('2026-09-05', $next->toDateString());
    }

    public function test_yearly_advances_respecting_the_calendar(): void
    {
        $anchor = Carbon::parse('2026-08-05');
        $current = Carbon::parse('2026-08-05');

        $next = RecurrenceDateResolver::next(RecurrenceFrequency::YEARLY, $anchor, $current);

        $this->assertSame('2027-08-05', $next->toDateString());
    }

    /**
     * Regra adotada para dia inexistente: o dia de ancoragem vem sempre de
     * start_date. Jan/31 -> Fev cai no último dia do mês (28/29), mas Mar
     * volta a tentar o dia 31 normalmente — sem ficar "preso" em 28.
     */
    public function test_monthly_on_day_31_falls_back_to_last_day_of_shorter_months_without_drifting(): void
    {
        $anchor = Carbon::parse('2026-01-31');
        $current = Carbon::parse('2026-01-31');

        $february = RecurrenceDateResolver::next(RecurrenceFrequency::MONTHLY, $anchor, $current);
        $this->assertSame('2026-02-28', $february->toDateString());

        $march = RecurrenceDateResolver::next(RecurrenceFrequency::MONTHLY, $anchor, $february);
        $this->assertSame('2026-03-31', $march->toDateString());

        $april = RecurrenceDateResolver::next(RecurrenceFrequency::MONTHLY, $anchor, $march);
        $this->assertSame('2026-04-30', $april->toDateString());

        $may = RecurrenceDateResolver::next(RecurrenceFrequency::MONTHLY, $anchor, $april);
        $this->assertSame('2026-05-31', $may->toDateString());
    }

    public function test_monthly_on_day_30_falls_back_to_february_28_on_non_leap_year(): void
    {
        $anchor = Carbon::parse('2027-01-30');
        $current = Carbon::parse('2027-01-30');

        $next = RecurrenceDateResolver::next(RecurrenceFrequency::MONTHLY, $anchor, $current);

        $this->assertSame('2027-02-28', $next->toDateString());
    }

    /**
     * Regra adotada para 29/02 em recorrência anual: em anos não bissextos
     * cai em 28/02; no próximo ano bissexto volta a cair em 29/02.
     */
    public function test_yearly_on_february_29_handles_leap_years_without_drifting(): void
    {
        $anchor = Carbon::parse('2024-02-29');
        $current = Carbon::parse('2024-02-29');

        $year2025 = RecurrenceDateResolver::next(RecurrenceFrequency::YEARLY, $anchor, $current);
        $this->assertSame('2025-02-28', $year2025->toDateString());

        $year2026 = RecurrenceDateResolver::next(RecurrenceFrequency::YEARLY, $anchor, $year2025);
        $this->assertSame('2026-02-28', $year2026->toDateString());

        $year2027 = RecurrenceDateResolver::next(RecurrenceFrequency::YEARLY, $anchor, $year2026);
        $this->assertSame('2027-02-28', $year2027->toDateString());

        $year2028 = RecurrenceDateResolver::next(RecurrenceFrequency::YEARLY, $anchor, $year2027);
        $this->assertSame('2028-02-29', $year2028->toDateString());
    }

    public function test_respects_a_custom_interval(): void
    {
        $anchor = Carbon::parse('2026-01-15');
        $current = Carbon::parse('2026-01-15');

        $weekly = RecurrenceDateResolver::next(RecurrenceFrequency::WEEKLY, $anchor, $current, 2);
        $this->assertSame('2026-01-29', $weekly->toDateString());

        $monthly = RecurrenceDateResolver::next(RecurrenceFrequency::MONTHLY, $anchor, $current, 3);
        $this->assertSame('2026-04-15', $monthly->toDateString());
    }
}
