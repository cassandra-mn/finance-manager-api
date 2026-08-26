<?php

namespace App\Repositories;

use App\Models\Goal;
use Illuminate\Database\Eloquent\Collection;

final class GoalRepository
{
    /** @return Collection<int, Goal> */
    public function listForUser(int $userId): Collection
    {
        return Goal::query()
            ->forUser($userId)
            ->with('account')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $attributes): Goal
    {
        return Goal::create($attributes);
    }

    public function update(Goal $goal, array $attributes): Goal
    {
        $goal->fill($attributes);
        $goal->save();

        return $goal;
    }

    public function delete(Goal $goal): void
    {
        $goal->delete();
    }
}
