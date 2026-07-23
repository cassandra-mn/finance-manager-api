<?php

namespace App\Data\Budgets;

use App\Http\Requests\Budgets\UpdateBudgetRequest;

final readonly class UpdateBudgetData
{
    public function __construct(
        public ?int $categoryId,
        public ?int $amountCents,
        public ?int $referenceMonth,
        public ?int $referenceYear,
    ) {}

    public static function fromRequest(UpdateBudgetRequest $request): self
    {
        return new self(
            categoryId: $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            amountCents: $request->filled('amount_cents') ? (int) $request->integer('amount_cents') : null,
            referenceMonth: $request->filled('reference_month') ? (int) $request->integer('reference_month') : null,
            referenceYear: $request->filled('reference_year') ? (int) $request->integer('reference_year') : null,
        );
    }
}
