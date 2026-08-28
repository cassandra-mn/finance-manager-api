<?php

namespace App\Data\Insights;

use App\Http\Requests\Insights\AnnualReportRequest;
use Illuminate\Support\Carbon;

final readonly class AnnualReportFiltersData
{
    public function __construct(
        public int $year,
        public int $topCategories,
    ) {}

    public static function fromRequest(AnnualReportRequest $request): self
    {
        return new self(
            year: $request->filled('year') ? (int) $request->integer('year') : Carbon::today()->year,
            topCategories: $request->filled('top_categories')
                ? (int) $request->integer('top_categories')
                : (int) config('insights.top_categories'),
        );
    }
}
