<?php

namespace App\Data\Budgets;

use App\Http\Requests\Budgets\ListBudgetsRequest;
use Illuminate\Support\Carbon;

final readonly class BudgetFiltersData
{
    public function __construct(
        public ?int $categoryId = null,
        public ?int $referenceMonth = null,
        public ?int $referenceYear = null,
    ) {}

    public static function fromRequest(ListBudgetsRequest $request): self
    {
        if ($request->filled('reference_date')) {
            $referenceDate = Carbon::parse($request->string('reference_date')->toString());

            return new self(
                categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
                referenceMonth: $referenceDate->month,
                referenceYear: $referenceDate->year,
            );
        }

        return new self(
            categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            referenceMonth: $request->filled('reference_month') ? (int) $request->integer('reference_month') : null,
            referenceYear: $request->filled('reference_year') ? (int) $request->integer('reference_year') : null,
        );
    }
}
