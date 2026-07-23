<?php

namespace App\Actions\Budgets;

use App\Data\Budgets\UpdateBudgetData;
use App\Models\Budget;
use Illuminate\Support\Facades\DB;

final class UpdateBudgetAction
{
    public function execute(Budget $budget, UpdateBudgetData $data): Budget
    {
        return DB::transaction(function () use ($budget, $data): Budget {
            $budget->fill(array_filter([
                'category_id' => $data->categoryId,
                'amount_cents' => $data->amountCents,
                'reference_month' => $data->referenceMonth,
                'reference_year' => $data->referenceYear,
            ], static fn (mixed $value): bool => $value !== null));

            $budget->save();

            return $budget;
        });
    }
}
