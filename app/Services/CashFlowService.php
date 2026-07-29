<?php

namespace App\Services;

use App\Http\Requests\CashFlow\CashFlowEvolutionRequest;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;

/**
 * Monta a evolução mensal do fluxo de caixa (receitas x despesas) do usuário
 * autenticado. A série de meses é sempre gerada primeiro, em memória, e os
 * totais de cada mês vêm de uma única consulta já filtrada por `user_id` —
 * assim um mês sem transações aparece zerado na resposta, nunca com dados de
 * outro usuário.
 */
final class CashFlowService
{
    public function __construct(
        private readonly TransactionRepository $repository,
    ) {}

    public function getEvolution(CashFlowEvolutionRequest $request): array
    {
        $referenceDate = $request->filled('reference_date')
            ? Carbon::parse($request->string('reference_date')->toString())
            : Carbon::today();

        $months = $request->filled('months') ? (int) $request->integer('months') : 6;

        $end = $referenceDate->copy()->endOfMonth();
        $start = $referenceDate->copy()->subMonths($months - 1)->startOfMonth();

        $transactions = $this->repository->forPeriodByUser($request->user()->id, $start, $end);

        $totalsByMonth = [];
        foreach ($transactions as $transaction) {
            $key = $transaction->due_date->format('Y-m');
            $totalsByMonth[$key] ??= ['income' => 0, 'expense' => 0];
            $totalsByMonth[$key][$transaction->type->value] += $transaction->amount_cents;
        }

        $series = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');
            $incomeCents = $totalsByMonth[$key]['income'] ?? 0;
            $expenseCents = $totalsByMonth[$key]['expense'] ?? 0;

            $series[] = [
                'year' => $cursor->year,
                'month' => $cursor->month,
                'period' => $key,
                'income_cents' => $incomeCents,
                'expense_cents' => $expenseCents,
                'balance_cents' => $incomeCents - $expenseCents,
            ];

            $cursor->addMonth();
        }

        return [
            'data' => $series,
            'summary' => $this->buildSummary($series),
        ];
    }

    /** @param  array<int, array{income_cents: int, expense_cents: int, balance_cents: int}>  $series */
    private function buildSummary(array $series): array
    {
        $totalIncomeCents = array_sum(array_column($series, 'income_cents'));
        $totalExpenseCents = array_sum(array_column($series, 'expense_cents'));

        return [
            'total_income_cents' => $totalIncomeCents,
            'total_expense_cents' => $totalExpenseCents,
            'total_balance_cents' => $totalIncomeCents - $totalExpenseCents,
        ];
    }
}
