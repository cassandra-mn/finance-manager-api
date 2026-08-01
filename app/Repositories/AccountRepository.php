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

    public function existsActiveForUser(int $userId, int $accountId): bool
    {
        return Account::query()
            ->whereKey($accountId)
            ->forUser($userId)
            ->active()
            ->exists();
    }
}
