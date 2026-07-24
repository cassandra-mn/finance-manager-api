<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budgets\BudgetStatusRequest;
use App\Http\Requests\Budgets\ListBudgetsRequest;
use App\Http\Requests\Budgets\StoreBudgetRequest;
use App\Http\Requests\Budgets\UpdateBudgetRequest;
use App\Http\Resources\Budgets\BudgetResource;
use App\Models\Budget;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $service,
    ) {}

    public function index(ListBudgetsRequest $request): JsonResponse
    {
        $budgets = $this->service->listForUser($request);

        return BudgetResource::collection($budgets)->response();
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $budget = $this->service->create($request);

        return (new BudgetResource($budget->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Budget $budget): JsonResponse
    {
        return (new BudgetResource($budget->load('category')))->response();
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        $budget = $this->service->update($budget, $request);

        return (new BudgetResource($budget->load('category')))->response();
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $this->service->delete($budget);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function status(BudgetStatusRequest $request): JsonResponse
    {
        return response()->json($this->service->getStatus($request));
    }
}
