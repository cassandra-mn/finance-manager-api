<?php

namespace App\Actions\Budgets;

use App\Models\Budget;

final class DeleteBudgetAction
{
    public function execute(Budget $budget): void
    {
        $budget->delete();
    }
}
