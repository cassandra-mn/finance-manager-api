<?php

namespace App\Services;

use App\Common\Money;
use App\Enum\BudgetStatus;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Calcula o consumo de cada orçamento a partir das transações do período. A
 * classificação safe/warning/exceeded é sempre decidida por comparação de
 * inteiros (centavos), nunca por float, para não haver erro de arredondamento
 * exatamente nos limites de 80% e 100%. `usage_percentage` é só apresentação.
 */
final class BudgetStatusService
{
    /**
     * @param  Collection<int, Budget>  $budgets
     * @return array<int, array{budget: Budget, spent_cents: int, remaining_cents: int, usage_percentage: float, status: BudgetStatus}>
     */
    public function calculate(int $userId, Collection $budgets, int $month, int $year): array
    {
        if ($budgets->isEmpty()) {
            return [];
        }

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $spentByCategory = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('category_id', $budgets->pluck('category_id'))
            ->where('type', TransactionType::EXPENSE->value)
            ->where('status', '!=', TransactionStatus::CANCELLED->value)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('category_id, SUM(amount_cents) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return $budgets->map(function (Budget $budget) use ($spentByCategory): array {
            $spentCents = (int) ($spentByCategory[$budget->category_id] ?? 0);
            $spent = Money::fromCents($spentCents);
            $amount = Money::fromCents($budget->amount_cents);

            return [
                'budget' => $budget,
                'spent_cents' => $spentCents,
                'remaining_cents' => $amount->subtract($spent)->cents,
                'usage_percentage' => round(($spentCents / $budget->amount_cents) * 100, 2),
                'status' => $this->resolveStatus($spent, $amount),
            ];
        })->all();
    }

    private function resolveStatus(Money $spent, Money $amount): BudgetStatus
    {
        if ($spent->greaterThan($amount)) {
            return BudgetStatus::EXCEEDED;
        }

        // Limiar de 80% via multiplicação cruzada de inteiros, não Money:
        // Money compara valor com valor, não valor com uma fração de outro
        // valor — aqui a comparação é de razão, não de quantia.
        if ($spent->cents * 100 >= $amount->cents * 80) {
            return BudgetStatus::WARNING;
        }

        return BudgetStatus::SAFE;
    }

    /**
     * Estende calculate() com uma extrapolação linear simples do gasto até o
     * fim do período ("nesse ritmo, vai gastar X"), com base no ritmo de
     * gasto até agora. $daysElapsed/$daysInPeriod entram como parâmetros
     * explícitos (não calculados aqui) para o método continuar puro e
     * testável sem depender do relógio, e para o chamador reaproveitar os
     * mesmos valores no envelope de resposta.
     *
     * @param  Collection<int, Budget>  $budgets
     * @return array<int, array{budget: Budget, spent_cents: int, remaining_cents: int, usage_percentage: float, status: BudgetStatus, projected_spent_cents: int, projected_overrun_cents: int, is_projected_to_exceed: bool}>
     */
    public function calculateWithProjection(int $userId, Collection $budgets, int $month, int $year, int $daysElapsed, int $daysInPeriod): array
    {
        $entries = $this->calculate($userId, $budgets, $month, $year);

        return array_map(function (array $entry) use ($daysElapsed, $daysInPeriod): array {
            $amount = Money::fromCents($entry['budget']->amount_cents);

            // Multiplica antes de dividir para não passar por float.
            $projectedSpent = Money::fromCents($daysElapsed > 0
                ? intdiv($entry['spent_cents'] * $daysInPeriod, $daysElapsed)
                : $entry['spent_cents']);

            $isProjectedToExceed = $projectedSpent->greaterThan($amount);
            $projectedOverrun = $isProjectedToExceed ? $projectedSpent->subtract($amount) : Money::zero();

            return $entry + [
                'projected_spent_cents' => $projectedSpent->cents,
                'projected_overrun_cents' => $projectedOverrun->cents,
                'is_projected_to_exceed' => $isProjectedToExceed,
            ];
        }, $entries);
    }
}
