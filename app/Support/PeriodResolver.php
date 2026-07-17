<?php

namespace App\Support;

use App\Enum\TransactionPeriod;
use Illuminate\Support\Carbon;

/**
 * Resolve um período configurável (semana, quinzena, mês) em um intervalo de
 * datas concreto, a partir de uma data de referência (padrão: hoje).
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
        };
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
}
