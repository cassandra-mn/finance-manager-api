<?php

namespace App\Services\Insights;

use App\Data\Insights\AnomalyDetectionFiltersData;
use App\Data\Insights\PartialPaymentsFiltersData;
use App\Data\Insights\SpendingSummaryFiltersData;
use App\Http\Requests\Insights\AnomalyDetectionRequest;
use App\Http\Requests\Insights\BudgetProjectionRequest;
use App\Http\Requests\Insights\PartialPaymentsRequest;
use App\Http\Requests\Insights\SpendingSummaryRequest;
use App\Http\Resources\Categories\CategoryResource;
use App\Repositories\BudgetRepository;
use App\Services\BudgetStatusService;
use App\Support\PeriodResolver;
use Illuminate\Support\Carbon;

/**
 * Orquestrador ciente de Request dos endpoints de Insights: resolve datas a
 * partir dos parâmetros da requisição, delega o cálculo puro para os
 * serviços dedicados, e monta o envelope reference_period/data/summary —
 * mesmo papel que BudgetService já cumpre para GET /budgets/status.
 */
final class InsightsService
{
    public function __construct(
        private readonly SpendingSummaryService $spendingSummaryService,
        private readonly AnomalyDetectionService $anomalyDetectionService,
        private readonly BudgetStatusService $budgetStatusService,
        private readonly PartialPaymentsService $partialPaymentsService,
        private readonly BudgetRepository $budgetRepository,
    ) {}

    public function spendingSummary(SpendingSummaryRequest $request): array
    {
        $filters = SpendingSummaryFiltersData::fromRequest($request);

        [$currentStart, $currentEnd] = PeriodResolver::resolve($filters->period, $filters->referenceDate);
        [$previousStart, $previousEnd] = PeriodResolver::previous($filters->period, $currentStart);

        $data = $this->spendingSummaryService->compare(
            $request->user()->id,
            $currentStart,
            $currentEnd,
            $previousStart,
            $previousEnd,
            $filters->topCategories,
        );

        return [
            'reference_period' => [
                'period' => $filters->period->value,
                'current' => ['from' => $currentStart->toDateString(), 'to' => $currentEnd->toDateString()],
                'previous' => ['from' => $previousStart->toDateString(), 'to' => $previousEnd->toDateString()],
            ],
            'data' => $data,
            'summary' => [
                'total_expense_cents' => $data['current']['expense_cents'],
                'top_categories_count' => count($data['top_categories']),
            ],
        ];
    }

    public function anomalies(AnomalyDetectionRequest $request): array
    {
        $filters = AnomalyDetectionFiltersData::fromRequest($request);

        [$currentStart, $currentEnd] = PeriodResolver::resolve($filters->period, $filters->referenceDate);

        $historicalEnd = $currentStart->copy()->subDay();
        $historicalStart = $currentStart->copy();

        for ($i = 0; $i < $filters->lookbackPeriods; $i++) {
            [$historicalStart] = PeriodResolver::previous($filters->period, $historicalStart);
        }

        $data = $this->anomalyDetectionService->detect(
            $request->user()->id,
            $filters->period,
            $currentStart,
            $currentEnd,
            $filters->lookbackPeriods,
            $filters->thresholdPercentage,
        );

        return [
            'reference_period' => [
                'period' => $filters->period->value,
                'current' => ['from' => $currentStart->toDateString(), 'to' => $currentEnd->toDateString()],
                'lookback_periods' => $filters->lookbackPeriods,
                'historical_window' => ['from' => $historicalStart->toDateString(), 'to' => $historicalEnd->toDateString()],
            ],
            'data' => $data,
            'summary' => [
                'threshold_percentage' => $filters->thresholdPercentage,
                'anomalies_count' => count(array_filter($data, fn (array $entry): bool => $entry['is_anomalous'])),
                'new_categories_count' => count(array_filter($data, fn (array $entry): bool => $entry['is_new_category'])),
            ],
        ];
    }

    public function partialPayments(PartialPaymentsRequest $request): array
    {
        $filters = PartialPaymentsFiltersData::fromRequest($request);

        $data = $this->partialPaymentsService->summarize(
            $request->user()->id,
            $filters->referenceDate,
            $filters->lookbackMonths,
        );

        return [
            'reference_period' => [
                'lookback_months' => $filters->lookbackMonths,
                'from' => $data[0]['month'] ?? null,
                'to' => $data[count($data) - 1]['month'] ?? null,
            ],
            'data' => $data,
            'summary' => [
                'total_shortfall_cents' => array_sum(array_column($data, 'shortfall_cents')),
                'total_count' => array_sum(array_column($data, 'count')),
            ],
        ];
    }

    public function budgetProjection(BudgetProjectionRequest $request): array
    {
        $referenceDate = $request->filled('reference_date')
            ? Carbon::parse($request->string('reference_date')->toString())
            : Carbon::today();

        $userId = $request->user()->id;
        $month = $referenceDate->month;
        $year = $referenceDate->year;

        $budgets = $this->budgetRepository->listForPeriod($userId, $month, $year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInPeriod = $start->daysInMonth;

        $today = Carbon::today();
        $projectionApplicable = $today->month === $month && $today->year === $year;

        if ($projectionApplicable) {
            $daysElapsed = $start->diffInDays($today) + 1;
            $daysRemaining = $daysInPeriod - $daysElapsed;
            $entries = $this->budgetStatusService->calculateWithProjection($userId, $budgets, $month, $year, $daysElapsed, $daysInPeriod);
        } else {
            $daysElapsed = null;
            $daysRemaining = null;
            $entries = $this->budgetStatusService->calculate($userId, $budgets, $month, $year);
        }

        return [
            'reference_period' => [
                'month' => $month,
                'year' => $year,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'days_in_period' => $daysInPeriod,
                'days_elapsed' => $daysElapsed,
                'days_remaining' => $daysRemaining,
                'projection_applicable' => $projectionApplicable,
            ],
            'data' => array_map(fn (array $entry): array => $this->formatBudgetEntry($entry, $projectionApplicable), $entries),
            'summary' => $this->buildBudgetSummary($entries, $projectionApplicable),
        ];
    }

    private function formatBudgetEntry(array $entry, bool $projectionApplicable): array
    {
        return [
            'id' => $entry['budget']->id,
            'category' => $entry['budget']->category
                ? (new CategoryResource($entry['budget']->category))->resolve()
                : null,
            'amount_cents' => $entry['budget']->amount_cents,
            'spent_cents' => $entry['spent_cents'],
            'remaining_cents' => $entry['remaining_cents'],
            'usage_percentage' => $entry['usage_percentage'],
            'status' => $entry['status']->value,
            'status_label' => $entry['status']->label(),
            'projected_spent_cents' => $projectionApplicable ? $entry['projected_spent_cents'] : null,
            'projected_overrun_cents' => $projectionApplicable ? $entry['projected_overrun_cents'] : null,
            'is_projected_to_exceed' => $projectionApplicable ? $entry['is_projected_to_exceed'] : null,
        ];
    }

    private function buildBudgetSummary(array $entries, bool $projectionApplicable): array
    {
        $totalBudgetCents = array_sum(array_map(fn (array $entry) => $entry['budget']->amount_cents, $entries));
        $totalSpentCents = array_sum(array_map(fn (array $entry) => $entry['spent_cents'], $entries));

        return [
            'total_budget_cents' => $totalBudgetCents,
            'total_spent_cents' => $totalSpentCents,
            'total_projected_spent_cents' => $projectionApplicable
                ? array_sum(array_map(fn (array $entry) => $entry['projected_spent_cents'], $entries))
                : null,
            'budgets_projected_to_exceed_count' => $projectionApplicable
                ? count(array_filter($entries, fn (array $entry): bool => $entry['is_projected_to_exceed']))
                : null,
        ];
    }
}
