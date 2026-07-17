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
}
