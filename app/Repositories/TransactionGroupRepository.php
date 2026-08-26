<?php

namespace App\Repositories;

use App\Data\TransactionGroups\TransactionGroupFiltersData;
use App\Enum\TransactionGroupStatus;
use App\Models\TransactionGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class TransactionGroupRepository
{
    /** @return Collection<int, TransactionGroup> */
    public function listForUser(int $userId, TransactionGroupFiltersData $filters): Collection
    {
        return TransactionGroup::query()
            ->forUser($userId)
            ->with(['account'])
            ->withCount('transactions')
            ->when($filters->accountId, fn (Builder $query) => $query->where('account_id', $filters->accountId))
            ->when($filters->type, fn (Builder $query) => $query->where('type', $filters->type->value))
            ->when($filters->status, fn (Builder $query) => $query->where('status', $filters->status->value))
            ->orderByDesc('reference_month')
            ->get();
    }

    public function findForAccountAndMonth(int $accountId, Carbon $referenceMonth): ?TransactionGroup
    {
        return TransactionGroup::query()
            ->forAccount($accountId)
            ->whereDate('reference_month', $referenceMonth->toDateString())
            ->first();
    }

    public function create(array $attributes): TransactionGroup
    {
        return TransactionGroup::create($attributes);
    }

    public function update(TransactionGroup $group, array $attributes): TransactionGroup
    {
        $group->fill($attributes);
        $group->save();

        return $group;
    }

    public function delete(TransactionGroup $group): void
    {
        $group->delete();
    }

    /**
     * Percorre, em blocos, as faturas abertas cujo fechamento já chegou.
     */
    public function chunkOpenClosingBy(Carbon $date, int $chunkSize, callable $callback): void
    {
        TransactionGroup::query()
            ->closingBy($date)
            ->chunkById($chunkSize, $callback);
    }

    /**
     * Faturas pagas parcialmente num intervalo de data de pagamento, com as
     * transações carregadas (necessário pra somar o valor de face da fatura,
     * que não é persistido — ver totalCents() no model). Usado por
     * PartialPaymentsService.
     *
     * @return Collection<int, TransactionGroup>
     */
    public function listPartiallyPaidBetween(int $userId, Carbon $from, Carbon $to): Collection
    {
        return TransactionGroup::query()
            ->forUser($userId)
            ->partiallyPaid()
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('transactions')
            ->get();
    }

    /**
     * Faturas ainda não pagas (abertas ou fechadas) com vencimento num
     * intervalo futuro — usado por CashFlowForecastService pra somar o
     * compromisso de cartão de crédito já conhecido em cada mês projetado.
     * Faturas pagas/parcialmente pagas ficam de fora (já resolvidas).
     *
     * @return Collection<int, TransactionGroup>
     */
    public function listUnpaidWithDueBetween(int $userId, Carbon $from, Carbon $to): Collection
    {
        return TransactionGroup::query()
            ->forUser($userId)
            ->whereIn('status', [TransactionGroupStatus::OPEN->value, TransactionGroupStatus::CLOSED->value])
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->with('transactions')
            ->get();
    }
}
