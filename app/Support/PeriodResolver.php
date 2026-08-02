<?php

namespace App\Support;

use App\Enum\TransactionPeriod;
use Illuminate\Support\Carbon;

/**
 * Resolve um período configurável (semana, quinzena, mês, trimestre, ano) em
 * um intervalo de datas concreto, a partir de uma data de referência (padrão:
 * hoje), e sabe calcular o período imediatamente anterior equivalente.
 */
final class PeriodResolver
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolve(TransactionPeriod $period, ?Carbon $reference = null): array
    {
        $reference ??= Carbon::today();

        return match ($period) {
            TransactionPeriod::WEEK => [
                $reference->copy()->startOfWeek(),
                $reference->copy()->endOfWeek(),
            ],
            TransactionPeriod::FORTNIGHT => self::resolveFortnight($reference),
            TransactionPeriod::MONTH => [
                $reference->copy()->startOfMonth(),
                $reference->copy()->endOfMonth(),
            ],
            TransactionPeriod::QUARTER => [
                $reference->copy()->startOfQuarter(),
                $reference->copy()->endOfQuarter(),
            ],
            TransactionPeriod::YEAR => [
                $reference->copy()->startOfYear(),
                $reference->copy()->endOfYear(),
            ],
        };
    }

    /**
     * Resolve o período imediatamente anterior ao que começa em $currentStart,
     * deslocando por unidade de calendário (nunca por contagem fixa de dias) —
     * "o mês anterior" precisa ser o mês de calendário anterior, não "30 dias
     * atrás". Quinzena é tratada como alternância dentro do mês: a anterior da
     * 1ª metade é a 2ª metade do mês anterior; a anterior da 2ª metade é a 1ª
     * metade do mesmo mês.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function previous(TransactionPeriod $period, Carbon $currentStart): array
    {
        $reference = match ($period) {
            TransactionPeriod::WEEK => $currentStart->copy()->subWeek(),
            TransactionPeriod::FORTNIGHT => self::previousFortnightReference($currentStart),
            TransactionPeriod::MONTH => $currentStart->copy()->subMonth(),
            TransactionPeriod::QUARTER => $currentStart->copy()->subQuarter(),
            TransactionPeriod::YEAR => $currentStart->copy()->subYear(),
        };

        return self::resolve($period, $reference);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolveFortnight(Carbon $reference): array
    {
        if ($reference->day <= 15) {
            return [
                $reference->copy()->startOfMonth(),
                $reference->copy()->startOfMonth()->addDays(14),
            ];
        }

        return [
            $reference->copy()->startOfMonth()->addDays(15),
            $reference->copy()->endOfMonth(),
        ];
    }

    private static function previousFortnightReference(Carbon $currentStart): Carbon
    {
        // $currentStart é sempre o dia 1 (1ª metade) ou o dia 16 (2ª metade),
        // já que resolveFortnight() só produz essas duas âncoras.
        return $currentStart->day === 1
            ? $currentStart->copy()->subMonth()->addDays(15)
            : $currentStart->copy()->startOfMonth();
    }
}
