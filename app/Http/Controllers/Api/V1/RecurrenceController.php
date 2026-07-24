<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recurrences\ListRecurrencesRequest;
use App\Http\Requests\Recurrences\StoreRecurrenceRequest;
use App\Http\Requests\Recurrences\UpdateRecurrenceRequest;
use App\Http\Resources\Recurrences\RecurrenceResource;
use App\Models\Recurrence;
use App\Services\RecurrenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RecurrenceController extends Controller
{
    public function __construct(
        private readonly RecurrenceService $service,
    ) {}

    public function index(ListRecurrencesRequest $request): JsonResponse
    {
        $recurrences = $this->service->listForUser($request);

        return RecurrenceResource::collection($recurrences)->response();
    }

    public function store(StoreRecurrenceRequest $request): JsonResponse
    {
        $recurrence = $this->service->create($request);

        return (new RecurrenceResource($recurrence->load(['account', 'category'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Recurrence $recurrence): JsonResponse
    {
        return (new RecurrenceResource($recurrence->load(['account', 'category'])))->response();
    }

    public function update(UpdateRecurrenceRequest $request, Recurrence $recurrence): JsonResponse
    {
        $recurrence = $this->service->update($recurrence, $request);

        return (new RecurrenceResource($recurrence->load(['account', 'category'])))->response();
    }

    public function destroy(Recurrence $recurrence): JsonResponse
    {
        $this->service->delete($recurrence);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function pause(Recurrence $recurrence): JsonResponse
    {
        $recurrence = $this->service->pause($recurrence);

        return (new RecurrenceResource($recurrence->load(['account', 'category'])))->response();
    }

    public function resume(Recurrence $recurrence): JsonResponse
    {
        $recurrence = $this->service->resume($recurrence);

        return (new RecurrenceResource($recurrence->load(['account', 'category'])))->response();
    }
}
