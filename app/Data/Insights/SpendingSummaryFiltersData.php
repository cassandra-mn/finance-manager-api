<?php

namespace App\Data\Insights;

use App\Enum\TransactionPeriod;
use App\Http\Requests\Insights\SpendingSummaryRequest;
use Illuminate\Support\Carbon;

final readonly class SpendingSummaryFiltersData
{
    public function __construct(
        public TransactionPeriod $period,
        public Carbon $referenceDate,
        public int $topCategories,
    ) {}

    public static function fromRequest(SpendingSummaryRequest $request): self
    {
        return new self(
            period: $request->filled('period')
                ? TransactionPeriod::from($request->string('period')->toString())
                : TransactionPeriod::MONTH,
            referenceDate: $request->filled('reference_date')
                ? Carbon::parse($request->string('reference_date')->toString())
                : Carbon::today(),
            topCategories: $request->filled('top_categories')
                ? (int) $request->integer('top_categories')
                : (int) config('insights.top_categories'),
        );
    }
}
