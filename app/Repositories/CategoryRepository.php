<?php

namespace App\Repositories;

use App\Enum\TransactionType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class CategoryRepository
{
    /** @return Collection<int, Category> */
    public function listForUser(int $userId, ?TransactionType $type = null): Collection
    {
        return Category::query()
            ->forUser($userId)
            ->when($type, fn (Builder $query) => $query->ofType($type))
            ->orderBy('name')
            ->get();
    }

    public function create(array $attributes): Category
    {
        return Category::create($attributes);
    }

    /** @param  list<array<string, mixed>>  $rows */
    public function createMany(array $rows): void
    {
        foreach ($rows as $row) {
            Category::create($row);
        }
    }

    public function update(Category $category, array $attributes): Category
    {
        $category->fill($attributes);
        $category->save();

        return $category;
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function existsById(int $categoryId): bool
    {
        return Category::query()->whereKey($categoryId)->exists();
    }
}
