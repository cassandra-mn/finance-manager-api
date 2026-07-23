<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Budgets\CreateBudgetAction;
use App\Actions\Budgets\DeleteBudgetAction;
use App\Actions\Budgets\GetBudgetStatusAction;
use App\Actions\Budgets\UpdateBudgetAction;
use App\Data\Budgets\BudgetFiltersData;
use App\Data\Budgets\CreateBudgetData;
use App\Data\Budgets\UpdateBudgetData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budgets\BudgetStatusRequest;
use App\Http\Requests\Budgets\ListBudgetsRequest;
use App\Http\Requests\Budgets\StoreBudgetRequest;
use App\Http\Requests\Budgets\UpdateBudgetRequest;
use App\Http\Resources\Budgets\BudgetResource;
use App\Models\Budget;
use App\Repositories\BudgetRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class BudgetController extends Controller
{
    public function index(ListBudgetsRequest $request, BudgetRepository $repository): JsonResponse
    {
        $budgets = $repository->listForUser(
            $request->user()->id,
            BudgetFiltersData::fromRequest($request),
        );

        return BudgetResource::collection($budgets)->response();
    }

    public function store(StoreBudgetRequest $request, CreateBudgetAction $action): JsonResponse
    {
        $budget = $action->execute(CreateBudgetData::fromRequest($request, $request->user()->id));

        return (new BudgetResource($budget->load('category')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Budget $budget): JsonResponse
    {
        return (new BudgetResource($budget->load('category')))->response();
    }

    public function update(UpdateBudgetRequest $request, Budget $budget, UpdateBudgetAction $action): JsonResponse
    {
        $budget = $action->execute($budget, UpdateBudgetData::fromRequest($request));

        return (new BudgetResource($budget->load('category')))->response();
    }

    public function destroy(Budget $budget, DeleteBudgetAction $action): JsonResponse
    {
        $action->execute($budget);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function status(BudgetStatusRequest $request, GetBudgetStatusAction $action): JsonResponse
    {
        $referenceDate = $request->filled('reference_date')
            ? Carbon::parse($request->string('reference_date')->toString())
            : Carbon::today();

        return response()->json($action->execute($request->user()->id, $referenceDate));
    }
}
