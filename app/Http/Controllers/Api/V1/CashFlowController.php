<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\CashFlowEvolutionRequest;
use App\Services\CashFlowService;
use Illuminate\Http\JsonResponse;

class CashFlowController extends Controller
{
    public function __construct(
        private readonly CashFlowService $service,
    ) {}

    public function evolution(CashFlowEvolutionRequest $request): JsonResponse
    {
        return response()->json($this->service->getEvolution($request));
    }
}
