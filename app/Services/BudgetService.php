<?php

namespace App\Services;

use App\Data\Budgets\BudgetFiltersData;
use App\Data\Budgets\CreateBudgetData;
use App\Data\Budgets\UpdateBudgetData;
use App\Enum\BudgetStatus;
use App\Exceptions\ServiceException;
use App\Http\Requests\Budgets\BudgetStatusRequest;
use App\Http\Requests\Budgets\ListBudgetsRequest;
use App\Http\Requests\Budgets\StoreBudgetRequest;
use App\Http\Requests\Budgets\UpdateBudgetRequest;
use App\Http\Resources\Budgets\BudgetStatusEntryResource;
use App\Models\Budget;
use App\Repositories\BudgetRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class BudgetService
{
    public function __construct(
        private readonly BudgetRepository $repository,
        private readonly BudgetStatusService $statusService,
        private readonly TransactionRepository $transactionRepository,
    ) {}

    /** @return Collection<int, Budget> */
    public function listForUser(ListBudgetsRequest $request): Collection
    {
        return $this->repository->listForUser(
            $request->user()->id,
            BudgetFiltersData::fromRequest($request),
        );
    }

    public function create(StoreBudgetRequest $request): Budget
    {
        $data = CreateBudgetData::fromRequest($request, $request->user()->id);

        try {
            return $this->repository->create([
                'user_id' => $data->userId,
                'category_id' => $data->categoryId,
                'amount_cents' => $data->amountCents,
                'reference_month' => $data->referenceMonth,
                'reference_year' => $data->referenceYear,
            ]);
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'budgets_user_category_period_unique')) {
                Log::error('finance.budgets.create_failed', [
                    'user_id' => $data->userId,
                    'message' => $e->getMessage(),
                ]);

                throw new ServiceException('Não foi possível criar o orçamento.', previous: $e);
            }

            throw ValidationException::withMessages([
                'category_id' => ['Já existe um orçamento para esta categoria neste período.'],
            ]);
        } catch (Throwable $e) {
            Log::error('finance.budgets.create_failed', [
                'user_id' => $data->userId,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível criar o orçamento.', previous: $e);
        }
    }

    public function update(Budget $budget, UpdateBudgetRequest $request): Budget
    {
        $data = UpdateBudgetData::fromRequest($request);

        try {
            return $this->repository->update($budget, array_filter([
                'category_id' => $data->categoryId,
                'amount_cents' => $data->amountCents,
                'reference_month' => $data->referenceMonth,
                'reference_year' => $data->referenceYear,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'budgets_user_category_period_unique')) {
                Log::error('finance.budgets.update_failed', [
                    'budget_id' => $budget->id,
                    'message' => $e->getMessage(),
                ]);

                throw new ServiceException('Não foi possível atualizar o orçamento.', previous: $e);
            }

            throw ValidationException::withMessages([
                'category_id' => ['Já existe um orçamento para esta categoria neste período.'],
            ]);
        } catch (Throwable $e) {
            Log::error('finance.budgets.update_failed', [
                'budget_id' => $budget->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível atualizar o orçamento.', previous: $e);
        }
    }

    public function delete(Budget $budget): void
    {
        try {
            $this->repository->delete($budget);
        } catch (Throwable $e) {
            Log::error('finance.budgets.delete_failed', [
                'budget_id' => $budget->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível excluir o orçamento.', previous: $e);
        }
    }

    public function getStatus(BudgetStatusRequest $request): array
    {
        $referenceDate = $request->filled('reference_date')
            ? Carbon::parse($request->string('reference_date')->toString())
            : Carbon::today();

        $month = $referenceDate->month;
        $year = $referenceDate->year;

        $budgets = $this->repository->listForPeriod($request->user()->id, $month, $year);
        $entries = $this->statusService->calculate($request->user()->id, $budgets, $month, $year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'reference_period' => [
                'month' => $month,
                'year' => $year,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'data' => array_map(
                static fn (array $entry): array => (new BudgetStatusEntryResource($entry))->resolve(),
                $entries,
            ),
            'summary' => $this->buildSummary($request->user()->id, $entries, $start, $end),
        ];
    }

    private function buildSummary(int $userId, array $entries, Carbon $start, Carbon $end): array
    {
        $totalBudgetCents = array_sum(array_map(fn (array $entry) => $entry['budget']->amount_cents, $entries));
        $totalSpentCents = array_sum(array_map(fn (array $entry) => $entry['spent_cents'], $entries));

        // Receita do período, pro modo de orçamento base-zero: cada real da
        // renda precisa ter um destino (uma categoria) até "não alocado"
        // chegar a zero — não é o mesmo conceito de total_remaining_cents
        // acima, que compara orçamento com gasto, não com renda.
        $incomeCents = $this->transactionRepository->sumTotalsForUser($userId, $start, $end)['income_cents'];

        return [
            'total_budget_cents' => $totalBudgetCents,
            'total_spent_cents' => $totalSpentCents,
            'total_remaining_cents' => $totalBudgetCents - $totalSpentCents,
            'income_cents' => $incomeCents,
            'unallocated_cents' => $incomeCents - $totalBudgetCents,
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
