<?php

namespace App\Services;

use App\Common\Money;
use App\Data\TransactionGroups\CloseDueCreditCardInvoicesSummary;
use App\Data\TransactionGroups\CreateTransactionGroupData;
use App\Data\TransactionGroups\PayTransactionGroupData;
use App\Data\TransactionGroups\TransactionGroupFiltersData;
use App\Data\TransactionGroups\UpdateTransactionGroupData;
use App\Enum\AccountType;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionGroupStatus;
use App\Enum\TransactionGroupType;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Exceptions\ServiceException;
use App\Http\Requests\TransactionGroups\ListTransactionGroupsRequest;
use App\Http\Requests\TransactionGroups\PayTransactionGroupRequest;
use App\Http\Requests\TransactionGroups\StoreTransactionGroupRequest;
use App\Http\Requests\TransactionGroups\UpdateTransactionGroupRequest;
use App\Models\Account;
use App\Models\TransactionGroup;
use App\Repositories\AccountRepository;
use App\Repositories\TransactionGroupRepository;
use App\Repositories\TransactionRepository;
use App\Support\CreditCardCycleResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Faturas de cartão de crédito normalmente são criadas/reaproveitadas
 * automaticamente (ver resolveOrCreateForCreditCardPurchase, chamado por
 * TransactionService e StatementImportService); create() cobre o caso de a
 * pessoa querer adiantar/registrar manualmente uma fatura de um mês
 * específico (ex.: um mês que ainda não teve nenhuma compra lançada).
 */
final class TransactionGroupService
{
    public function __construct(
        private readonly TransactionGroupRepository $repository,
        private readonly TransactionRepository $transactionRepository,
        private readonly AccountRepository $accountRepository,
    ) {}

    /** @return Collection<int, TransactionGroup> */
    public function listForUser(ListTransactionGroupsRequest $request): Collection
    {
        return $this->repository->listForUser(
            $request->user()->id,
            TransactionGroupFiltersData::fromRequest($request),
        );
    }

    /**
     * Resolve a fatura do ciclo ao qual uma compra pertence, criando-a se
     * ainda não existir. Retorna null quando a fatura do ciclo já foi paga
     * (a compra fica avulsa em vez de reabrir uma fatura quitada).
     */
    public function resolveOrCreateForCreditCardPurchase(Account $account, Carbon $purchaseDate): ?TransactionGroup
    {
        $cycle = CreditCardCycleResolver::resolve($account, $purchaseDate);

        $group = $this->repository->findForAccountAndMonth($account->id, $cycle->referenceMonth);

        if ($group === null) {
            try {
                $group = $this->repository->create([
                    'user_id' => $account->user_id,
                    'account_id' => $account->id,
                    'type' => TransactionGroupType::CREDIT_CARD_INVOICE,
                    'reference_month' => $cycle->referenceMonth,
                    'closing_date' => $cycle->closingDate,
                    'due_date' => $cycle->dueDate,
                    'status' => TransactionGroupStatus::OPEN,
                ]);
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'transaction_groups_account_reference_month_unique')) {
                    throw $e;
                }

                $group = $this->repository->findForAccountAndMonth($account->id, $cycle->referenceMonth);
            }
        }

        if ($group?->status === TransactionGroupStatus::PAID) {
            Log::warning('finance.transaction_groups.purchase_after_paid_invoice', [
                'account_id' => $account->id,
                'transaction_group_id' => $group->id,
            ]);

            return null;
        }

        return $group;
    }

    /**
     * Cria manualmente uma fatura vazia para um cartão/mês escolhido pela
     * pessoa (ex.: registrar uma fatura de um mês passado que ainda não foi
     * lançada no app). Usa o mesmo cálculo de ciclo de resolveOrCreate...,
     * só que partindo do mês de referência em vez de uma data de compra.
     */
    public function create(StoreTransactionGroupRequest $request): TransactionGroup
    {
        $data = CreateTransactionGroupData::fromRequest($request);
        $account = $this->accountRepository->find($data->accountId);

        if ($account === null || $account->type !== AccountType::CREDIT_CARD || $account->invoice_due_day === null) {
            throw ValidationException::withMessages([
                'account_id' => ['Selecione um cartão de crédito com dia de vencimento configurado.'],
            ]);
        }

        $existing = $this->repository->findForAccountAndMonth($account->id, $data->referenceMonth);
        if ($existing !== null) {
            $label = $data->referenceMonth->translatedFormat('F/Y');

            throw ValidationException::withMessages([
                'reference_month' => ["Já existe uma fatura para {$label} neste cartão."],
            ]);
        }

        $cycle = CreditCardCycleResolver::forReferenceMonth($account, $data->referenceMonth);

        try {
            return $this->repository->create([
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'type' => TransactionGroupType::CREDIT_CARD_INVOICE,
                'reference_month' => $cycle->referenceMonth,
                'closing_date' => $cycle->closingDate,
                'due_date' => $cycle->dueDate,
                'status' => TransactionGroupStatus::OPEN,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.transaction_groups.create_failed', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível criar a fatura.', previous: $e);
        }
    }

    public function update(TransactionGroup $group, UpdateTransactionGroupRequest $request): TransactionGroup
    {
        if ($group->isPaid()) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível editar uma fatura já paga.'],
            ]);
        }

        $data = UpdateTransactionGroupData::fromRequest($request);

        try {
            return $this->repository->update($group, array_filter([
                'name' => $data->name,
                'notes' => $data->notes,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (Throwable $e) {
            Log::error('finance.transaction_groups.update_failed', [
                'transaction_group_id' => $group->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível atualizar a fatura.', previous: $e);
        }
    }

    /**
     * Exclui a fatura e todas as transações lançadas nela (soft delete).
     */
    public function delete(TransactionGroup $group): void
    {
        if ($group->isPaid() || $group->isPartiallyPaid()) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível excluir uma fatura já paga.'],
            ]);
        }

        try {
            DB::transaction(function () use ($group): void {
                $this->transactionRepository->deleteForGroup($group->id);
                $this->repository->delete($group);
            });
        } catch (Throwable $e) {
            Log::error('finance.transaction_groups.delete_failed', [
                'transaction_group_id' => $group->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível excluir a fatura.', previous: $e);
        }
    }

    public function close(TransactionGroup $group): TransactionGroup
    {
        if (! $group->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['Só é possível fechar uma fatura aberta.'],
            ]);
        }

        try {
            return $this->repository->update($group, ['status' => TransactionGroupStatus::CLOSED]);
        } catch (Throwable $e) {
            Log::error('finance.transaction_groups.close_failed', [
                'transaction_group_id' => $group->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível fechar a fatura.', previous: $e);
        }
    }

    /**
     * Paga a fatura. Um amount_cents menor que o total registra um pagamento
     * parcial (status PARTIALLY_PAID) — a transação de liquidação criada usa
     * só o valor efetivamente pago (é isso que debita a conta pagadora), e o
     * restante não vira uma nova pendência no app, só fica registrado para
     * fins de estatística (ver PartialPaymentsService).
     */
    public function pay(TransactionGroup $group, PayTransactionGroupRequest $request): TransactionGroup
    {
        if ($group->isPaid()) {
            throw ValidationException::withMessages([
                'status' => ['Esta fatura já foi paga.'],
            ]);
        }

        if ($group->isPartiallyPaid()) {
            throw ValidationException::withMessages([
                'status' => ['Esta fatura já foi paga parcialmente.'],
            ]);
        }

        if (! $group->isClosed()) {
            throw ValidationException::withMessages([
                'status' => ['A fatura precisa estar fechada antes de ser paga.'],
            ]);
        }

        $data = PayTransactionGroupData::fromRequest($request);

        $totalAmount = Money::fromCents((int) $group->transactions()->sum('amount_cents'));
        $paidAmount = $data->amountCents !== null ? Money::fromCents($data->amountCents) : null;

        if ($paidAmount !== null && $paidAmount->greaterThan($totalAmount)) {
            throw ValidationException::withMessages([
                'amount_cents' => ['O valor pago não pode ser maior que o valor da fatura.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($group, $data, $totalAmount, $paidAmount): TransactionGroup {
                $paidAmount ??= $totalAmount;
                $isPartial = $paidAmount->lessThan($totalAmount);

                $paymentTransaction = $this->transactionRepository->create([
                    'user_id' => $group->user_id,
                    'account_id' => $data->paymentAccountId,
                    'category_id' => null,
                    'type' => TransactionType::EXPENSE,
                    'entry_type' => TransactionEntryType::SINGLE,
                    'status' => TransactionStatus::PAID,
                    'description' => 'Pagamento '.$group->display_name,
                    'amount_cents' => $paidAmount->cents,
                    'due_date' => $data->paidAt->toDateString(),
                    'paid_at' => $data->paidAt,
                ]);

                return $this->repository->update($group, [
                    'status' => $isPartial ? TransactionGroupStatus::PARTIALLY_PAID : TransactionGroupStatus::PAID,
                    'paid_amount_cents' => $paidAmount->cents,
                    'paid_at' => $data->paidAt,
                    'payment_account_id' => $data->paymentAccountId,
                    'payment_transaction_id' => $paymentTransaction->id,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('finance.transaction_groups.pay_failed', [
                'transaction_group_id' => $group->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível pagar a fatura.', previous: $e);
        }
    }

    public function closeDueInvoices(?Carbon $referenceDate = null): CloseDueCreditCardInvoicesSummary
    {
        $referenceDate = ($referenceDate ?? Carbon::today())->copy()->startOfDay();
        $summary = new CloseDueCreditCardInvoicesSummary;

        $this->repository->chunkOpenClosingBy(
            $referenceDate,
            100,
            function (Collection $groups) use ($summary): void {
                foreach ($groups as $group) {
                    try {
                        $this->repository->update($group, ['status' => TransactionGroupStatus::CLOSED]);
                        $summary->invoicesClosed++;
                    } catch (Throwable $e) {
                        $summary->errors++;
                        Log::error('finance.transaction_groups.auto_close_failed', [
                            'transaction_group_id' => $group->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            },
        );

        return $summary;
    }
}
