<?php

namespace App\Repositories;

use App\Data\Budgets\BudgetFiltersData;
use App\Models\Budget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class BudgetRepository
{
    /** @return Collection<int, Budget> */
    public function listForUser(int $userId, BudgetFiltersData $filters): Collection
    {
        return Budget::query()
            ->where('user_id', $userId)
            ->with('category')
            ->when($filters->categoryId, fn (Builder $query) => $query->where('category_id', $filters->categoryId))
            ->when($filters->referenceMonth, fn (Builder $query) => $query->where('reference_month', $filters->referenceMonth))
            ->when($filters->referenceYear, fn (Builder $query) => $query->where('reference_year', $filters->referenceYear))
            ->orderBy('reference_year', 'desc')
            ->orderBy('reference_month', 'desc')
            ->get();
    }

    /** @return Collection<int, Budget> */
    public function listForPeriod(int $userId, int $month, int $year): Collection
    {
        return Budget::query()
            ->where('user_id', $userId)
            ->where('reference_month', $month)
            ->where('reference_year', $year)
            ->with('category')
            ->get();
    }
}
