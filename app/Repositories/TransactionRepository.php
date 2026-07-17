<?php

namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TransactionRepository
{
    public function paginateForUser(int $userId): LengthAwarePaginator
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->with(['account', 'category'])
            ->orderBy('due_date')
            ->paginate();
    }
}
