<?php

namespace App\Repositories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;

final class AccountRepository
{
    /** @return Collection<int, Account> */
    public function listForUser(int $userId): Collection
    {
        return Account::query()
            ->forUser($userId)
            ->orderBy('name')
            ->get();
    }

    public function create(array $attributes): Account
    {
        return Account::create($attributes);
    }

    public function update(Account $account, array $attributes): Account
    {
        $account->fill($attributes);
        $account->save();

        return $account;
    }

    public function delete(Account $account): void
    {
        $account->delete();
    }

    public function existsActiveById(int $accountId): bool
    {
        return Account::query()
            ->whereKey($accountId)
            ->active()
            ->exists();
    }

    /**
     * Busca (incluindo soft-deleted) a conta local já importada para uma
     * conta externa da Pluggy. Sem escopo de usuário autenticado — usada
     * pela sincronização, que roda fora de um contexto de request.
     */
    public function findByExternalAccount(int $bankConnectionId, string $externalAccountId): ?Account
    {
        return Account::query()
            ->withoutGlobalScope('belongsToUser')
            ->withTrashed()
            ->where('bank_connection_id', $bankConnectionId)
            ->where('external_account_id', $externalAccountId)
            ->first();
    }

    public function createFromExternal(array $attributes): Account
    {
        return Account::create($attributes);
    }
}
