<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionGroups\ListTransactionGroupsRequest;
use App\Http\Requests\TransactionGroups\PayTransactionGroupRequest;
use App\Http\Requests\TransactionGroups\StoreTransactionGroupRequest;
use App\Http\Requests\TransactionGroups\UpdateTransactionGroupRequest;
use App\Http\Resources\TransactionGroups\TransactionGroupResource;
use App\Models\TransactionGroup;
use App\Services\TransactionGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TransactionGroupController extends Controller
{
    public function __construct(
        private readonly TransactionGroupService $service,
    ) {}

    public function index(ListTransactionGroupsRequest $request): JsonResponse
    {
        $groups = $this->service->listForUser($request);

        return TransactionGroupResource::collection($groups)->response();
    }

    public function store(StoreTransactionGroupRequest $request): JsonResponse
    {
        $transactionGroup = $this->service->create($request);

        return (new TransactionGroupResource($transactionGroup->load(['account'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(TransactionGroup $transactionGroup): JsonResponse
    {
        return (new TransactionGroupResource($transactionGroup->load(['account', 'paymentAccount', 'transactions.category'])))->response();
    }

    public function update(UpdateTransactionGroupRequest $request, TransactionGroup $transactionGroup): JsonResponse
    {
        $transactionGroup = $this->service->update($transactionGroup, $request);

        return (new TransactionGroupResource($transactionGroup->load(['account'])))->response();
    }

    public function destroy(TransactionGroup $transactionGroup): JsonResponse
    {
        $this->service->delete($transactionGroup);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function pay(PayTransactionGroupRequest $request, TransactionGroup $transactionGroup): JsonResponse
    {
        $transactionGroup = $this->service->pay($transactionGroup, $request);

        return (new TransactionGroupResource($transactionGroup->load(['account', 'paymentAccount'])))->response();
    }

    public function close(TransactionGroup $transactionGroup): JsonResponse
    {
        $transactionGroup = $this->service->close($transactionGroup);

        return (new TransactionGroupResource($transactionGroup->load(['account'])))->response();
    }
}
