<?php

namespace App\Services\Insights;

use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;

/**
 * Compara receita/despesa do período atual com o período anterior
 * equivalente, e monta o ranking das categorias que mais pesaram no gasto.
 * Cálculo puro — não depende de Request, só de datas já resolvidas.
 */
final class SpendingSummaryService
{
    public function __construct(
        private readonly TransactionRepository $repository,
    ) {}

    /**
     * @return array{
     *     current: array{income_cents: int, expense_cents: int},
     *     previous: array{income_cents: int, expense_cents: int},
     *     delta: array{income_cents: int, expense_cents: int, income_percentage: ?float, expense_percentage: ?float},
     *     top_categories: list<array{category_id: int, category_name: string, category_color: ?string, amount_cents: int, percentage_of_total: float}>,
     *     others_cents: int,
     * }
     */
    public function compare(
        int $userId,
        Carbon $currentStart,
        Carbon $currentEnd,
        Carbon $previousStart,
        Carbon $previousEnd,
        int $topCategories,
    ): array {
        $current = $this->repository->sumTotalsForUser($userId, $currentStart, $currentEnd);
        $previous = $this->repository->sumTotalsForUser($userId, $previousStart, $previousEnd);

        $categories = $this->repository->sumExpensesByCategory($userId, $currentStart, $currentEnd);
        $totalExpenseCents = (int) $categories->sum('total_cents');

        $topCategoriesList = $categories->take($topCategories)->map(fn (object $category): array => [
            'category_id' => (int) $category->category_id,
            'category_name' => $category->category_name,
            'category_color' => $category->category_color,
            'amount_cents' => (int) $category->total_cents,
            'percentage_of_total' => $totalExpenseCents > 0
                ? round(((int) $category->total_cents) / $totalExpenseCents * 100, 2)
                : 0.0,
        ])->values()->all();

        $othersCents = $totalExpenseCents - array_sum(array_column($topCategoriesList, 'amount_cents'));

        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => [
                'income_cents' => $current['income_cents'] - $previous['income_cents'],
                'expense_cents' => $current['expense_cents'] - $previous['expense_cents'],
                'income_percentage' => $this->percentageChange($current['income_cents'], $previous['income_cents']),
                'expense_percentage' => $this->percentageChange($current['expense_cents'], $previous['expense_cents']),
            ],
            'top_categories' => $topCategoriesList,
            'others_cents' => $othersCents,
        ];
    }

    private function percentageChange(int $currentCents, int $previousCents): ?float
    {
        if ($previousCents === 0) {
            return null;
        }

        return round(($currentCents - $previousCents) / $previousCents * 100, 2);
    }
}
