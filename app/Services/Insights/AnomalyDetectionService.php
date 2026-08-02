<?php

namespace App\Services\Insights;

use App\Enum\TransactionPeriod;
use App\Repositories\TransactionRepository;
use App\Support\PeriodResolver;
use Illuminate\Support\Carbon;

/**
 * Compara, por categoria, o gasto do período atual com a média histórica dos
 * períodos anteriores, sinalizando categorias que fogem muito do padrão
 * (limiar configurável) — e, da mesma conta, categorias sem histórico algum
 * (gasto novo). Cálculo puro — não depende de Request.
 *
 * O limiar nunca é comparado via float: a checagem é feita por multiplicação
 * cruzada de inteiros (mesma disciplina de BudgetStatusService::resolveStatus()),
 * evitando erro de arredondamento exatamente na borda do limiar.
 */
final class AnomalyDetectionService
{
    public function __construct(
        private readonly TransactionRepository $repository,
    ) {}

    /**
     * @return list<array{
     *     category_id: int, category_name: string, category_color: ?string,
     *     current_cents: int, average_cents: int, deviation_percentage: ?float,
     *     is_anomalous: bool, is_new_category: bool,
     * }>
     */
    public function detect(
        int $userId,
        TransactionPeriod $period,
        Carbon $currentStart,
        Carbon $currentEnd,
        int $lookbackPeriods,
        int $thresholdPercentage,
    ): array {
        $historicalEnd = $currentStart->copy()->subDay();
        $historicalStart = $currentStart->copy();

        for ($i = 0; $i < $lookbackPeriods; $i++) {
            [$historicalStart] = PeriodResolver::previous($period, $historicalStart);
        }

        $current = $this->repository->sumExpensesByCategory($userId, $currentStart, $currentEnd)->keyBy('category_id');
        $historical = $this->repository->sumExpensesByCategory($userId, $historicalStart, $historicalEnd)->keyBy('category_id');

        $categoryIds = $current->keys()->merge($historical->keys())->unique();

        return $categoryIds->map(function (int $categoryId) use ($current, $historical, $lookbackPeriods, $thresholdPercentage): array {
            $currentRow = $current->get($categoryId);
            $historicalRow = $historical->get($categoryId);

            $currentCents = (int) ($currentRow->total_cents ?? 0);
            $historicalSumCents = (int) ($historicalRow->total_cents ?? 0);
            $averageCents = intdiv($historicalSumCents, $lookbackPeriods);

            $isAnomalous = $currentCents * 100 > $averageCents * (100 + $thresholdPercentage);
            $isNewCategory = $historicalSumCents === 0 && $currentCents > 0;

            return [
                'category_id' => $categoryId,
                'category_name' => $currentRow->category_name ?? $historicalRow->category_name,
                'category_color' => $currentRow->category_color ?? $historicalRow->category_color,
                'current_cents' => $currentCents,
                'average_cents' => $averageCents,
                'deviation_percentage' => $averageCents > 0
                    ? round(($currentCents - $averageCents) / $averageCents * 100, 2)
                    : null,
                'is_anomalous' => $isAnomalous,
                'is_new_category' => $isNewCategory,
            ];
        })->values()->all();
    }
}
