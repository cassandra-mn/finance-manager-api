<?php

namespace App\Repositories;

use App\Enum\TransactionType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class CategoryRepository
{
    /** @return Collection<int, Category> */
    public function listForUser(int $userId, ?TransactionType $type = null): Collection
    {
        return Category::query()
            ->where('user_id', $userId)
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->orderBy('name')
            ->get();
    }
}
