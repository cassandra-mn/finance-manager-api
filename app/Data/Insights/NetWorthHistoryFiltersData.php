<?php

namespace App\Data\Insights;

use App\Http\Requests\Insights\NetWorthHistoryRequest;
use Illuminate\Support\Carbon;

final readonly class NetWorthHistoryFiltersData
{
    public function __construct(
        public Carbon $referenceDate,
        public int $lookbackMonths,
    ) {}

    public static function fromRequest(NetWorthHistoryRequest $request): self
    {
        return new self(
            referenceDate: $request->filled('reference_date')
                ? Carbon::parse($request->string('reference_date')->toString())
                : Carbon::today(),
            lookbackMonths: $request->filled('lookback_months')
                ? (int) $request->integer('lookback_months')
                : (int) config('insights.net_worth_history.lookback_months'),
        );
    }
}
