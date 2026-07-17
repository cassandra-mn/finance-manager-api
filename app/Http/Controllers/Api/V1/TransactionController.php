<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transactions\CreateTransactionAction;
use App\Actions\Transactions\DeleteTransactionAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Data\Transactions\CreateTransactionData;
use App\Data\Transactions\UpdateTransactionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionRepository $repository): JsonResponse
    {
        $transactions = $repository->paginateForUser($request->user()->id);

        return TransactionResource::collection($transactions)->response();
    }

    public function store(StoreTransactionRequest $request, CreateTransactionAction $action): JsonResponse
    {
        $transaction = $action->execute(CreateTransactionData::fromRequest($request, $request->user()->id));

        return (new TransactionResource($transaction->load(['account', 'category'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return (new TransactionResource($transaction->load(['account', 'category'])))->response();
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction, UpdateTransactionAction $action): JsonResponse
    {
        $transaction = $action->execute($transaction, UpdateTransactionData::fromRequest($request));

        return (new TransactionResource($transaction->load(['account', 'category'])))->response();
    }

    public function destroy(Transaction $transaction, DeleteTransactionAction $action): JsonResponse
    {
        $action->execute($transaction);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
