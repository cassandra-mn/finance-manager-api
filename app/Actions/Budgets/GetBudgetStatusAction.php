<?php

namespace App\Actions\Budgets;

use App\Enum\BudgetStatus;
use App\Http\Resources\Categories\CategoryResource;
use App\Repositories\BudgetRepository;
use App\Services\BudgetStatusService;
use Illuminate\Support\Carbon;

final class GetBudgetStatusAction
{
    public function __construct(
        private readonly BudgetRepository $repository,
        private readonly BudgetStatusService $statusService,
    ) {}

    public function execute(int $userId, Carbon $referenceDate): array
    {
        $month = $referenceDate->month;
        $year = $referenceDate->year;

        $budgets = $this->repository->listForPeriod($userId, $month, $year);
        $entries = $this->statusService->calculate($userId, $budgets, $month, $year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'reference_period' => [
                'month' => $month,
                'year' => $year,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'data' => array_map($this->formatEntry(...), $entries),
            'summary' => $this->buildSummary($entries),
        ];
    }

    private function formatEntry(array $entry): array
    {
        return [
            'id' => $entry['budget']->id,
            'category' => (new CategoryResource($entry['budget']->category))->resolve(),
            'amount_cents' => $entry['budget']->amount_cents,
            'spent_cents' => $entry['spent_cents'],
            'remaining_cents' => $entry['remaining_cents'],
            'usage_percentage' => $entry['usage_percentage'],
            'status' => $entry['status']->value,
            'status_label' => $entry['status']->label(),
        ];
    }

    private function buildSummary(array $entries): array
    {
        $totalBudgetCents = array_sum(array_map(fn (array $entry) => $entry['budget']->amount_cents, $entries));
        $totalSpentCents = array_sum(array_map(fn (array $entry) => $entry['spent_cents'], $entries));

        return [
            'total_budget_cents' => $totalBudgetCents,
            'total_spent_cents' => $totalSpentCents,
            'total_remaining_cents' => $totalBudgetCents - $totalSpentCents,
            'safe_count' => $this->countByStatus($entries, BudgetStatus::SAFE),
            'warning_count' => $this->countByStatus($entries, BudgetStatus::WARNING),
            'exceeded_count' => $this->countByStatus($entries, BudgetStatus::EXCEEDED),
        ];
    }

    private function countByStatus(array $entries, BudgetStatus $status): int
    {
        return count(array_filter($entries, static fn (array $entry): bool => $entry['status'] === $status));
    }
}
