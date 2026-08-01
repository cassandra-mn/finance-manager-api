<?php

namespace App\Repositories;

use App\Models\BankConnection;
use Illuminate\Database\Eloquent\Collection;

final class BankConnectionRepository
{
    /** @return Collection<int, BankConnection> */
    public function listForUser(int $userId): Collection
    {
        return BankConnection::query()
            ->forUser($userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForUser(int $userId, int $id): ?BankConnection
    {
        return BankConnection::query()
            ->forUser($userId)
            ->find($id);
    }

    public function findByPluggyItemId(string $pluggyItemId): ?BankConnection
    {
        return BankConnection::query()
            ->withoutGlobalScope('belongsToUser')
            ->where('pluggy_item_id', $pluggyItemId)
            ->first();
    }

    public function create(array $attributes): BankConnection
    {
        return BankConnection::create($attributes);
    }

    public function save(BankConnection $connection): void
    {
        $connection->save();
    }

    public function delete(BankConnection $connection): void
    {
        $connection->delete();
    }

    /**
     * Todas as conexões ativas de todos os usuários, usadas pelo comando
     * agendado — deliberadamente sem escopo de usuário autenticado.
     *
     * @return Collection<int, BankConnection>
     */
    public function listSyncable(): Collection
    {
        return BankConnection::query()
            ->withoutGlobalScope('belongsToUser')
            ->syncable()
            ->get();
    }
}
