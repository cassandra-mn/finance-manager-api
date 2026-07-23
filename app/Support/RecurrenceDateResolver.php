<?php

namespace App\Support;

use App\Enum\RecurrenceFrequency;
use Illuminate\Support\Carbon;

/**
 * Calcula a próxima data de ocorrência de uma recorrência a partir da
 * ocorrência atual, respeitando o calendário.
 *
 * Para `monthly`/`yearly`, o dia (e o mês, no caso anual) de ancoragem vem
 * sempre de `start_date` — nunca da ocorrência anterior. Isso evita "drift":
 * uma recorrência mensal no dia 31 tenta o dia 31 todo mês; em fevereiro ela
 * cai no último dia do mês (28 ou 29), mas em março volta a tentar o dia 31
 * normalmente, em vez de ficar presa em 28. O mesmo vale para 29/02 em
 * recorrências anuais: em anos não bissextos ela cai em 28/02, e volta a
 * cair em 29/02 no próximo ano bissexto.
 */
final class RecurrenceDateResolver
{
    public static function next(RecurrenceFrequency $frequency, Carbon $anchor, Carbon $current, int $interval = 1): Carbon
    {
        $interval = max(1, $interval);

        return match ($frequency) {
            RecurrenceFrequency::WEEKLY => $current->copy()->addDays(7 * $interval),
            RecurrenceFrequency::FORTNIGHTLY => $current->copy()->addDays(14 * $interval),
            RecurrenceFrequency::MONTHLY => self::nextMonthly($anchor, $current, $interval),
            RecurrenceFrequency::YEARLY => self::nextYearly($anchor, $current, $interval),
        };
    }

    private static function nextMonthly(Carbon $anchor, Carbon $current, int $interval): Carbon
    {
        $next = $current->copy()->startOfMonth()->addMonthsNoOverflow($interval);

        return $next->day(min($anchor->day, $next->daysInMonth));
    }

    private static function nextYearly(Carbon $anchor, Carbon $current, int $interval): Carbon
    {
        $next = $current->copy()->startOfYear()->addYears($interval)->month($anchor->month);

        return $next->day(min($anchor->day, $next->daysInMonth));
    }
}
