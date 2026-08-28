<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Insights\AnnualReportRequest;
use App\Http\Requests\Insights\AnomalyDetectionRequest;
use App\Http\Requests\Insights\BudgetProjectionRequest;
use App\Http\Requests\Insights\CashFlowForecastRequest;
use App\Http\Requests\Insights\NetWorthHistoryRequest;
use App\Http\Requests\Insights\PartialPaymentsRequest;
use App\Http\Requests\Insights\RecurringCommitmentsRequest;
use App\Http\Requests\Insights\SpendingSummaryRequest;
use App\Services\Insights\InsightsService;
use Illuminate\Http\JsonResponse;

class InsightsController extends Controller
{
    public function __construct(
        private readonly InsightsService $service,
    ) {}

    public function spendingSummary(SpendingSummaryRequest $request): JsonResponse
    {
        return response()->json($this->service->spendingSummary($request));
    }

    public function anomalies(AnomalyDetectionRequest $request): JsonResponse
    {
        return response()->json($this->service->anomalies($request));
    }

    public function budgetProjection(BudgetProjectionRequest $request): JsonResponse
    {
        return response()->json($this->service->budgetProjection($request));
    }

    public function partialPayments(PartialPaymentsRequest $request): JsonResponse
    {
        return response()->json($this->service->partialPayments($request));
    }

    public function cashFlowForecast(CashFlowForecastRequest $request): JsonResponse
    {
        return response()->json($this->service->cashFlowForecast($request));
    }

    public function netWorthHistory(NetWorthHistoryRequest $request): JsonResponse
    {
        return response()->json($this->service->netWorthHistory($request));
    }

    public function recurringCommitments(RecurringCommitmentsRequest $request): JsonResponse
    {
        return response()->json($this->service->recurringCommitments($request));
    }

    public function annualReport(AnnualReportRequest $request): JsonResponse
    {
        return response()->json($this->service->annualReport($request));
    }
}
