<?php

namespace App\Data\Budgets;

use App\Http\Requests\Budgets\StoreBudgetRequest;

final readonly class CreateBudgetData
{
    public function __construct(
        public int $userId,
        public int $categoryId,
        public int $amountCents,
        public int $referenceMonth,
        public int $referenceYear,
    ) {}

    public static function fromRequest(StoreBudgetRequest $request, int $userId): self
    {
        return new self(
            userId: $userId,
            categoryId: (int) $request->integer('category_id'),
            amountCents: (int) $request->integer('amount_cents'),
            referenceMonth: (int) $request->integer('reference_month'),
            referenceYear: (int) $request->integer('reference_year'),
        );
    }
}
