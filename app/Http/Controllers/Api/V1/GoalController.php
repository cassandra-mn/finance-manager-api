<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Goals\ListGoalsRequest;
use App\Http\Requests\Goals\StoreGoalRequest;
use App\Http\Requests\Goals\UpdateGoalRequest;
use App\Http\Resources\Goals\GoalResource;
use App\Models\Goal;
use App\Services\GoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GoalController extends Controller
{
    public function __construct(
        private readonly GoalService $service,
    ) {}

    public function index(ListGoalsRequest $request): JsonResponse
    {
        $goals = $this->service->listForUser($request);

        return GoalResource::collection($this->service->withProgress($goals))->response();
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $goal = $this->service->create($request)->load('account');

        return (new GoalResource($this->service->singleWithProgress($goal)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Goal $goal): JsonResponse
    {
        $goal->load('account');

        return (new GoalResource($this->service->singleWithProgress($goal)))->response();
    }

    public function update(UpdateGoalRequest $request, Goal $goal): JsonResponse
    {
        $goal = $this->service->update($goal, $request)->load('account');

        return (new GoalResource($this->service->singleWithProgress($goal)))->response();
    }

    public function destroy(Goal $goal): JsonResponse
    {
        $this->service->delete($goal);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
