<?php

namespace App\Repositories;

use App\Models\StatementImport;
use Illuminate\Database\Eloquent\Collection;

final class StatementImportRepository
{
    /** @return Collection<int, StatementImport> */
    public function listForAccount(int $userId, int $accountId): Collection
    {
        return StatementImport::query()
            ->forUser($userId)
            ->forAccount($accountId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(array $attributes): StatementImport
    {
        return StatementImport::create($attributes);
    }
}
