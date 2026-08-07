<?php

namespace App\Services\Insights;

use App\Repositories\TransactionGroupRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;

/**
 * Soma, por mês, o quanto ficou pendente em pagamentos parciais (transações
 * avulsas e faturas) num intervalo de meses. Puramente estatístico: esse
 * valor não é uma pendência rastreada no resto do app (ver
 * TransactionService::markAsPaid e TransactionGroupService::pay) — a pessoa
 * resolveu o restante fora do app (parcelado no banco, embutido na próxima
 * fatura etc.), então isso só serve pra dar visibilidade histórica.
 */
final class PartialPaymentsService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly TransactionGroupRepository $transactionGroupRepository,
    ) {}

    /**
     * @return list<array{month: string, month_label: string, shortfall_cents: int, count: int}>
     */
    public function summarize(int $userId, Carbon $referenceDate, int $lookbackMonths): array
    {
        $end = $referenceDate->copy()->endOfMonth();
        $start = $referenceDate->copy()->subMonths($lookbackMonths - 1)->startOfMonth();

        $months = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $months[$key] = [
                'month' => $key,
                'month_label' => $cursor->translatedFormat('F/Y'),
                'shortfall_cents' => 0,
                'count' => 0,
            ];
            $cursor->addMonthNoOverflow();
        }

        $transactionRows = $this->transactionRepository->sumPartiallyPaidShortfallByMonth($userId, $start, $end);

        foreach ($transactionRows as $row) {
            if (! isset($months[$row->month])) {
                continue;
            }

            $months[$row->month]['shortfall_cents'] += (int) $row->shortfall_cents;
            $months[$row->month]['count'] += (int) $row->count;
        }

        $groups = $this->transactionGroupRepository->listPartiallyPaidBetween($userId, $start, $end);

        foreach ($groups as $group) {
            $key = $group->paid_at?->format('Y-m');

            if ($key === null || ! isset($months[$key])) {
                continue;
            }

            $totalCents = (int) $group->transactions->sum('amount_cents');
            $months[$key]['shortfall_cents'] += $totalCents - (int) $group->paid_amount_cents;
            $months[$key]['count']++;
        }

        return array_values($months);
    }
}
