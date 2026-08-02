<?php

namespace App\Data\Insights;

use App\Enum\TransactionPeriod;
use App\Http\Requests\Insights\AnomalyDetectionRequest;
use Illuminate\Support\Carbon;

final readonly class AnomalyDetectionFiltersData
{
    public function __construct(
        public TransactionPeriod $period,
        public Carbon $referenceDate,
        public int $lookbackPeriods,
        public int $thresholdPercentage,
    ) {}

    public static function fromRequest(AnomalyDetectionRequest $request): self
    {
        return new self(
            period: $request->filled('period')
                ? TransactionPeriod::from($request->string('period')->toString())
                : TransactionPeriod::MONTH,
            referenceDate: $request->filled('reference_date')
                ? Carbon::parse($request->string('reference_date')->toString())
                : Carbon::today(),
            lookbackPeriods: $request->filled('lookback_periods')
                ? (int) $request->integer('lookback_periods')
                : (int) config('insights.anomaly.lookback_periods'),
            thresholdPercentage: $request->filled('threshold_percentage')
                ? (int) $request->integer('threshold_percentage')
                : (int) config('insights.anomaly.threshold_percentage'),
        );
    }
}
