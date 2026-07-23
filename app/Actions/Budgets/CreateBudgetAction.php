<?php

namespace App\Actions\Budgets;

use App\Data\Budgets\CreateBudgetData;
use App\Models\Budget;
use Illuminate\Support\Facades\DB;

final class CreateBudgetAction
{
    public function execute(CreateBudgetData $data): Budget
    {
        return DB::transaction(fn (): Budget => Budget::create([
            'user_id' => $data->userId,
            'category_id' => $data->categoryId,
            'amount_cents' => $data->amountCents,
            'reference_month' => $data->referenceMonth,
            'reference_year' => $data->referenceYear,
        ]));
    }
}
