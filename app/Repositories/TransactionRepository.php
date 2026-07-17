<?php

namespace App\Repositories;

use App\Data\Transactions\TransactionFiltersData;
use App\Enum\TransactionDisplayStatus;
use App\Enum\TransactionStatus;
use App\Models\Transaction;
use App\Support\PeriodResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class TransactionRepository
{
    public function paginateForUser(int $userId, TransactionFiltersData $filters): LengthAwarePaginator
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->with(['account', 'category'])
            ->when($filters->accountId, fn (Builder $query) => $query->where('account_id', $filters->accountId))
            ->when($filters->categoryId, fn (Builder $query) => $query->where('category_id', $filters->categoryId))
            ->when($filters->type, fn (Builder $query) => $query->where('type', $filters->type->value))
            ->when($filters->entryType, fn (Builder $query) => $query->where('entry_type', $filters->entryType->value))
            ->when($filters->search, fn (Builder $query) => $query->where('description', 'ilike', "%{$filters->search}%"))
            ->when($filters->status, fn (Builder $query) => $this->applyStatusFilter($query, $filters->status))
            ->when($filters->period, function (Builder $query) use ($filters): void {
                [$from, $to] = PeriodResolver::resolve($filters->period);
                $query->whereBetween('due_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->when($filters->from, fn (Builder $query) => $query->whereDate('due_date', '>=', $filters->from))
            ->when($filters->to, fn (Builder $query) => $query->whereDate('due_date', '<=', $filters->to))
            ->orderBy('due_date')
            ->paginate($filters->perPage);
    }

    private function applyStatusFilter(Builder $query, TransactionDisplayStatus $status): Builder
    {
        return match ($status) {
            TransactionDisplayStatus::OVERDUE => $query->overdue(),
            TransactionDisplayStatus::PENDING => $query->where('status', TransactionStatus::PENDING->value)
                ->where(fn (Builder $q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', Carbon::today())),
            TransactionDisplayStatus::PAID => $query->where('status', TransactionStatus::PAID->value),
            TransactionDisplayStatus::CANCELLED => $query->where('status', TransactionStatus::CANCELLED->value),
        };
    }
}
