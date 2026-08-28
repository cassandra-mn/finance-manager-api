<?php

namespace App\Data\Insights;

use App\Http\Requests\Insights\DebtPayoffPlanRequest;

final readonly class DebtPayoffPlanFiltersData
{
    public function __construct(
        public int $accountId,
        public int $monthlyPaymentCents,
        public float $annualInterestRatePercentage,
    ) {}

    public static function fromRequest(DebtPayoffPlanRequest $request): self
    {
        return new self(
            accountId: (int) $request->integer('account_id'),
            monthlyPaymentCents: (int) $request->integer('monthly_payment_cents'),
            annualInterestRatePercentage: $request->float('annual_interest_rate_percentage', 0.0),
        );
    }
}
