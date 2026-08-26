<?php

namespace App\Repositories;

use App\Data\Recurrences\RecurrenceFiltersData;
use App\Models\Recurrence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class RecurrenceRepository
{
    /** @return Collection<int, Recurrence> */
    public function listForUser(int $userId, RecurrenceFiltersData $filters): Collection
    {
        return Recurrence::query()
            ->forUser($userId)
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

    public function create(array $attributes): Recurrence
    {
        return Recurrence::create($attributes);
    }

    public function update(Recurrence $recurrence, array $attributes): Recurrence
    {
        $recurrence->fill($attributes);
        $recurrence->save();

        return $recurrence;
    }

    public function delete(Recurrence $recurrence): void
    {
        $recurrence->delete();
    }

    public function save(Recurrence $recurrence): void
    {
        $recurrence->save();
    }

    /**
     * Percorre, em blocos, as recorrências ativas com vencimento até $windowEnd.
     */
    public function chunkActiveDueBy(Carbon $windowEnd, int $chunkSize, callable $callback): void
    {
        Recurrence::query()
            ->active()
            ->dueBy($windowEnd)
            ->chunkById($chunkSize, $callback);
    }

    /**
     * Todas as recorrências ativas do usuário, sem filtro de janela — usado
     * por CashFlowForecastService pra projetar ocorrências futuras a partir
     * de next_due_date (indo além da janela curta de geração automática) e
     * por RecurringCommitmentsService pra somar o custo mensal/anual
     * comprometido. account/category vêm eager-carregados pro segundo caso,
     * que exibe nome/cor de cada um sem custo de N+1.
     *
     * @return Collection<int, Recurrence>
     */
    public function listActiveForUser(int $userId): Collection
    {
        return Recurrence::query()
            ->forUser($userId)
            ->with(['account', 'category'])
            ->active()
            ->get();
    }
}
