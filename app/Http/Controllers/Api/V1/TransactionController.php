<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\ListTransactionsRequest;
use App\Http\Requests\Transactions\PayTransactionRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Http\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(ListTransactionsRequest $request): StreamedResponse
    {
        $transactions = $this->service->listForExport($request);

        return response()->streamDownload(function () use ($transactions): void {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pra abrir com acentuação correta direto no Excel.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Descrição', 'Categoria', 'Tipo', 'Natureza', 'Status', 'Valor (R$)', 'Conta', 'Notas'], ';');

            foreach ($transactions as $transaction) {
                fputcsv($handle, [
                    $transaction->due_date?->toDateString(),
                    $transaction->description,
                    $transaction->category?->name ?? '',
                    $transaction->type->label(),
                    $transaction->entry_type->label(),
                    $transaction->effective_display_status->label(),
                    number_format($transaction->amount_cents / 100, 2, ',', '.'),
                    $transaction->account?->name ?? '',
                    $transaction->notes ?? '',
                ], ';');
            }

            fclose($handle);
        }, 'transacoes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $transaction = $this->service->create($request);

        return (new TransactionResource($transaction->load(['account', 'category'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return (new TransactionResource($transaction->load(['account', 'category'])))->response();
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $transaction = $this->service->update($transaction, $request);

        return (new TransactionResource($transaction->load(['account', 'category'])))->response();
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $this->service->delete($transaction);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function pay(PayTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $transaction = $this->service->markAsPaid($transaction, $request->integer('amount_cents') ?: null);

        return (new TransactionResource($transaction->load(['account', 'category'])))->response();
    }

    public function cancel(Transaction $transaction): JsonResponse
    {
        $transaction = $this->service->cancel($transaction);

        return (new TransactionResource($transaction->load(['account', 'category'])))->response();
    }

    public function unpay(Transaction $transaction): JsonResponse
    {
        $transaction = $this->service->markAsPending($transaction);

        return (new TransactionResource($transaction->load(['account', 'category'])))->response();
    }
}
