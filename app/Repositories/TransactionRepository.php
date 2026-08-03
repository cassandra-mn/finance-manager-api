<?php

namespace App\Repositories;

use App\Data\Transactions\TransactionFiltersData;
use App\Enum\TransactionDisplayStatus;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Transaction;
use App\Support\PeriodResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class TransactionRepository
{
    public function paginateForUser(int $userId, TransactionFiltersData $filters): LengthAwarePaginator
    {
        return Transaction::query()
            ->forUser($userId)
            ->with(['account', 'category'])
            ->when($filters->accountId, fn (Builder $query) => $query->where('account_id', $filters->accountId))
            ->when($filters->categoryId, fn (Builder $query) => $query->where('category_id', $filters->categoryId))
            ->when($filters->type, fn (Builder $query) => $query->where('type', $filters->type->value))
            ->when($filters->entryType, fn (Builder $query) => $query->where('entry_type', $filters->entryType->value))
            ->when($filters->search, fn (Builder $query) => $query->where('description', 'ilike', "%{$filters->search}%"))
            ->when($filters->transactionGroupId, fn (Builder $query) => $query->where('transaction_group_id', $filters->transactionGroupId))
            ->when(! is_null($filters->grouped), fn (Builder $query) => $filters->grouped
                ? $query->whereNotNull('transaction_group_id')
                : $query->whereNull('transaction_group_id'))
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
            TransactionDisplayStatus::PENDING => $query->pending()
                ->where(fn (Builder $q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', Carbon::today())),
            TransactionDisplayStatus::PAID => $query->paid(),
            TransactionDisplayStatus::CANCELLED => $query->cancelled(),
        };
    }

    public function create(array $attributes): Transaction
    {
        return Transaction::create($attributes);
    }

    public function update(Transaction $transaction, array $attributes): Transaction
    {
        $transaction->fill($attributes);
        $transaction->save();

        return $transaction;
    }

    public function delete(Transaction $transaction): void
    {
        $transaction->delete();
    }

    public function existsForRecurrenceOnDate(int $recurrenceId, Carbon $dueDate): bool
    {
        return Transaction::query()
            ->forRecurrence($recurrenceId)
            ->dueOn($dueDate)
            ->exists();
    }

    public function createFromExternal(array $attributes): Transaction
    {
        return Transaction::create($attributes);
    }

    /** @return Collection<int, Transaction> */
    public function listRecentForUser(int $userId, int $limit = 5): Collection
    {
        return Transaction::query()
            ->forUser($userId)
            ->latest('due_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Soma receitas/despesas do usuário num intervalo, mesmo critério de
     * "gasto" já usado por BudgetStatusService::calculate() (exclui apenas
     * cancelled — pending e paid contam).
     *
     * @return array{income_cents: int, expense_cents: int}
     */
    public function sumTotalsForUser(int $userId, Carbon $from, Carbon $to): array
    {
        $totals = Transaction::query()
            ->forUser($userId)
            ->where('status', '!=', TransactionStatus::CANCELLED->value)
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('type, SUM(amount_cents) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'income_cents' => (int) ($totals[TransactionType::INCOME->value] ?? 0),
            'expense_cents' => (int) ($totals[TransactionType::EXPENSE->value] ?? 0),
        ];
    }

    /**
     * Soma despesas do usuário por categoria num intervalo, ordenado do maior
     * para o menor gasto.
     *
     * @return Collection<int, object{category_id:int, category_name:string, category_color:?string, total_cents:int}>
     */
    public function sumExpensesByCategory(int $userId, Carbon $from, Carbon $to): Collection
    {
        return Transaction::query()
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', TransactionType::EXPENSE->value)
            ->where('transactions.status', '!=', TransactionStatus::CANCELLED->value)
            ->whereBetween('transactions.due_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('transactions.category_id as category_id, categories.name as category_name, categories.color as category_color, SUM(transactions.amount_cents) as total_cents')
            ->groupBy('transactions.category_id', 'categories.name', 'categories.color')
            ->orderByDesc('total_cents')
            ->get();
    }
}
