<?php

namespace App\Services;

use App\Common\Money;
use App\Data\Transactions\CreateTransactionData;
use App\Data\Transactions\TransactionFiltersData;
use App\Data\Transactions\UpdateTransactionData;
use App\Enum\AccountType;
use App\Enum\TransactionStatus;
use App\Exceptions\ServiceException;
use App\Http\Requests\Transactions\ListTransactionsRequest;
use App\Http\Requests\Transactions\StoreTransactionRequest;
use App\Http\Requests\Transactions\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Repositories\AccountRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class TransactionService
{
    public function __construct(
        private readonly TransactionRepository $repository,
        private readonly AccountRepository $accountRepository,
        private readonly TransactionGroupService $transactionGroupService,
    ) {}

    public function paginateForUser(ListTransactionsRequest $request): LengthAwarePaginator
    {
        return $this->repository->paginateForUser(
            $request->user()->id,
            TransactionFiltersData::fromRequest($request),
        );
    }

    public function create(StoreTransactionRequest $request): Transaction
    {
        $data = CreateTransactionData::fromRequest($request, $request->user()->id);

        try {
            $account = $this->accountRepository->find($data->accountId);
            $transactionGroupId = null;

            if ($account?->type === AccountType::CREDIT_CARD && $account->invoice_due_day !== null) {
                $transactionGroupId = $this->transactionGroupService
                    ->resolveOrCreateForCreditCardPurchase($account, $data->dueDate)?->id;
            }

            return $this->repository->create([
                'user_id' => $data->userId,
                'account_id' => $data->accountId,
                'category_id' => $data->categoryId,
                'transaction_group_id' => $transactionGroupId,
                'type' => $data->type,
                'entry_type' => $data->entryType,
                'status' => TransactionStatus::PENDING,
                'description' => $data->description,
                'amount_cents' => $data->amountCents,
                'due_date' => $data->dueDate,
                'notes' => $data->notes,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.transactions.create_failed', [
                'user_id' => $data->userId,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível criar a transação.', previous: $e);
        }
    }

    public function update(Transaction $transaction, UpdateTransactionRequest $request): Transaction
    {
        $this->guardAgainstPaidGroup($transaction);

        $data = UpdateTransactionData::fromRequest($request);

        try {
            return $this->repository->update($transaction, array_filter([
                'account_id' => $data->accountId,
                'category_id' => $data->categoryId,
                'type' => $data->type,
                'entry_type' => $data->entryType,
                'description' => $data->description,
                'amount_cents' => $data->amountCents,
                'due_date' => $data->dueDate,
                'notes' => $data->notes,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (Throwable $e) {
            Log::error('finance.transactions.update_failed', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível atualizar a transação.', previous: $e);
        }
    }

    public function delete(Transaction $transaction): void
    {
        $this->guardAgainstPaidGroup($transaction);

        try {
            $this->repository->delete($transaction);
        } catch (Throwable $e) {
            Log::error('finance.transactions.delete_failed', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível excluir a transação.', previous: $e);
        }
    }

    /**
     * Marca a transação como paga. Um $paidAmountCents menor que o valor da
     * transação registra um pagamento parcial (status PARTIALLY_PAID) — o
     * restante não vira uma nova pendência no app (normalmente é resolvido
     * fora dele, ex.: parcelado no banco), só fica registrado para fins de
     * estatística (ver PartialPaymentsService).
     */
    public function markAsPaid(Transaction $transaction, ?int $paidAmountCents = null): Transaction
    {
        if ($transaction->isGrouped()) {
            throw ValidationException::withMessages([
                'status' => ['Esta transação faz parte de uma fatura — pague a fatura em vez da transação.'],
            ]);
        }

        if ($transaction->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível pagar uma transação cancelada.'],
            ]);
        }

        $totalAmount = Money::fromCents($transaction->amount_cents);
        $paidAmount = $paidAmountCents !== null ? Money::fromCents($paidAmountCents) : null;

        if ($paidAmount !== null && $paidAmount->greaterThan($totalAmount)) {
            throw ValidationException::withMessages([
                'amount_cents' => ['O valor pago não pode ser maior que o valor da transação.'],
            ]);
        }

        $isPartial = $paidAmount !== null && $paidAmount->lessThan($totalAmount);

        try {
            return $this->repository->update($transaction, [
                'status' => $isPartial ? TransactionStatus::PARTIALLY_PAID : TransactionStatus::PAID,
                'paid_amount_cents' => $isPartial ? $paidAmount->cents : $totalAmount->cents,
                'paid_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            Log::error('finance.transactions.mark_as_paid_failed', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível marcar a transação como paga.', previous: $e);
        }
    }

    /**
     * Reverte uma transação paga (ou parcialmente paga) de volta para
     * pendente — ex.: a pessoa marcou como paga sem querer. Limpa o valor e
     * a data de pagamento; não afeta o saldo retroativamente, o saldo já se
     * recalcula sozinho a partir do status atual.
     */
    public function markAsPending(Transaction $transaction): Transaction
    {
        if (! $transaction->isPaid() && ! $transaction->isPartiallyPaid()) {
            throw ValidationException::withMessages([
                'status' => ['Só é possível reverter para pendente uma transação paga ou parcialmente paga.'],
            ]);
        }

        try {
            return $this->repository->update($transaction, [
                'status' => TransactionStatus::PENDING,
                'paid_amount_cents' => null,
                'paid_at' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.transactions.mark_as_pending_failed', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível reverter a transação para pendente.', previous: $e);
        }
    }

    public function cancel(Transaction $transaction): Transaction
    {
        if ($transaction->isGrouped()) {
            throw ValidationException::withMessages([
                'status' => ['Esta transação faz parte de uma fatura — não pode ser cancelada individualmente.'],
            ]);
        }

        if ($transaction->isPaid()) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível cancelar uma transação já paga.'],
            ]);
        }

        try {
            return $this->repository->update($transaction, [
                'status' => TransactionStatus::CANCELLED,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.transactions.cancel_failed', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível cancelar a transação.', previous: $e);
        }
    }

    private function guardAgainstPaidGroup(Transaction $transaction): void
    {
        if ($transaction->isGrouped() && ($transaction->group?->isPaid() || $transaction->group?->isPartiallyPaid())) {
            throw ValidationException::withMessages([
                'transaction_group_id' => ['Não é possível alterar uma transação de uma fatura já paga.'],
            ]);
        }
    }
}
