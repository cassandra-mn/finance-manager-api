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
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();
    }
}
