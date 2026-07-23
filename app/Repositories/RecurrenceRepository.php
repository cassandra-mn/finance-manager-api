<?php

namespace App\Repositories;

use App\Data\Recurrences\RecurrenceFiltersData;
use App\Models\Recurrence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class RecurrenceRepository
{
    /** @return Collection<int, Recurrence> */
    public function listForUser(int $userId, RecurrenceFiltersData $filters): Collection
    {
        return Recurrence::query()
            ->where('user_id', $userId)
            ->with(['account', 'category'])
            ->when($filters->accountId, fn (Builder $query) => $query->where('account_id', $filters->accountId))
            ->when($filters->categoryId, fn (Builder $query) => $query->where('category_id', $filters->categoryId))
            ->when($filters->type, fn (Builder $query) => $query->where('type', $filters->type->value))
            ->when($filters->frequency, fn (Builder $query) => $query->where('frequency', $filters->frequency->value))
            ->when(! is_null($filters->isActive), fn (Builder $query) => $query->where('is_active', $filters->isActive))
            ->when($filters->search, fn (Builder $query) => $query->whereLike('description', "%{$filters->search}%"))
            ->orderBy('next_due_date')
            ->get();
    }
}
