<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recurrences\CreateRecurringRuleAction;
use App\Actions\Recurrences\DeleteRecurringRuleAction;
use App\Actions\Recurrences\PauseRecurringRuleAction;
use App\Actions\Recurrences\ResumeRecurringRuleAction;
use App\Actions\Recurrences\UpdateRecurringRuleAction;
use App\Data\Recurrences\CreateRecurrenceData;
use App\Data\Recurrences\RecurrenceFiltersData;
use App\Data\Recurrences\UpdateRecurrenceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recurrences\ListRecurrencesRequest;
use App\Http\Requests\Recurrences\StoreRecurrenceRequest;
use App\Http\Requests\Recurrences\UpdateRecurrenceRequest;
use App\Http\Resources\Recurrences\RecurrenceResource;
use App\Models\Recurrence;
use App\Repositories\RecurrenceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RecurrenceController extends Controller
{
    public function index(ListRecurrencesRequest $request, RecurrenceRepository $repository): JsonResponse
    {
        $recurrences = $repository->listForUser(
            $request->user()->id,
            RecurrenceFiltersData::fromRequest($request),
        );

        return RecurrenceResource::collection($recurrences)->response();
    }

    public function store(StoreRecurrenceRequest $request, CreateRecurringRuleAction $action): JsonResponse
    {
        $recurrence = $action->execute(CreateRecurrenceData::fromRequest($request, $request->user()->id));

        return (new RecurrenceResource($recurrence->load(['account', 'category'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Recurrence $recurrence): JsonResponse
    {
        return (new RecurrenceResource($recurrence->load(['account', 'category'])))->response();
    }

    public function update(UpdateRecurrenceRequest $request, Recurrence $recurrence, UpdateRecurringRuleAction $action): JsonResponse
    {
        $recurrence = $action->execute($recurrence, UpdateRecurrenceData::fromRequest($request));

        return (new RecurrenceResource($recurrence->load(['account', 'category'])))->response();
    }

    public function destroy(Recurrence $recurrence, DeleteRecurringRuleAction $action): JsonResponse
    {
        $action->execute($recurrence);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function pause(Recurrence $recurrence, PauseRecurringRuleAction $action): JsonResponse
    {
        $recurrence = $action->execute($recurrence);

        return (new RecurrenceResource($recurrence->load(['account', 'category'])))->response();
    }

    public function resume(Recurrence $recurrence, ResumeRecurringRuleAction $action): JsonResponse
    {
        $recurrence = $action->execute($recurrence);

        return (new RecurrenceResource($recurrence->load(['account', 'category'])))->response();
    }
}
