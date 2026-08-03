<?php

namespace App\Repositories;

use App\Data\TransactionGroups\TransactionGroupFiltersData;
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

    /**
     * Percorre, em blocos, as faturas abertas cujo fechamento já chegou.
     */
    public function chunkOpenClosingBy(Carbon $date, int $chunkSize, callable $callback): void
    {
        TransactionGroup::query()
            ->closingBy($date)
            ->chunkById($chunkSize, $callback);
    }
}
