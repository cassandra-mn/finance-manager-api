<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\ListTransactionsRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $service,
    ) {}

    public function index(ListTransactionsRequest $request): JsonResponse
    {
        $transactions = $this->service->paginateForUser($request);

        return TransactionResource::collection($transactions)->response();
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $transaction = $this->service->create($request);

        return (new TransactionResource($transaction->load(['account', 'category', 'group'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return (new TransactionResource($transaction->load(['account', 'category', 'group'])))->response();
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $transaction = $this->service->update($transaction, $request);

        return (new TransactionResource($transaction->load(['account', 'category', 'group'])))->response();
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $this->service->delete($transaction);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function pay(Transaction $transaction): JsonResponse
    {
        $transaction = $this->service->markAsPaid($transaction);

        return (new TransactionResource($transaction->load(['account', 'category', 'group'])))->response();
    }

    public function cancel(Transaction $transaction): JsonResponse
    {
        $transaction = $this->service->cancel($transaction);

        return (new TransactionResource($transaction->load(['account', 'category', 'group'])))->response();
    }
}
