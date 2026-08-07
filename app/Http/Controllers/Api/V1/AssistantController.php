<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\AskQuestionRequest;
use App\Http\Requests\Assistant\QuickAddRequest;
use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AiAssistantService $service,
    ) {}

    public function quickAdd(QuickAddRequest $request): JsonResponse
    {
        $result = $this->service->quickAdd($request->string('message')->toString(), $request->user());

        return response()->json($result);
    }

    public function askQuestion(AskQuestionRequest $request): JsonResponse
    {
        $result = $this->service->askQuestion($request->string('message')->toString(), $request->user());

        return response()->json($result);
    }
}
