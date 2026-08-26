<?php

namespace App\Data\Insights;

use App\Http\Requests\Insights\CashFlowForecastRequest;
use Illuminate\Support\Carbon;

final readonly class CashFlowForecastFiltersData
{
    public function __construct(
        public Carbon $referenceDate,
        public int $months,
    ) {}

    public static function fromRequest(CashFlowForecastRequest $request): self
    {
        return new self(
            referenceDate: $request->filled('reference_date')
                ? Carbon::parse($request->string('reference_date')->toString())
                : Carbon::today(),
            months: $request->filled('months')
                ? (int) $request->integer('months')
                : (int) config('insights.cash_flow_forecast.months'),
        );
    }
}
