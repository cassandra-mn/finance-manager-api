<?php

namespace App\Http\Resources\Goals;

use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property array{goal: Goal, current_cents: int} $resource
 */
class GoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Goal $goal */
        $goal = $this->resource['goal'];
        $currentCents = $this->resource['current_cents'];
        $targetCents = $goal->target_cents;

        $isAchieved = $currentCents >= $targetCents;
        $progressPercentage = $targetCents > 0
            ? min(100.0, round(($currentCents / $targetCents) * 100, 2))
            : 0.0;

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'target_cents' => $targetCents,
            'current_cents' => $currentCents,
            'remaining_cents' => max(0, $targetCents - $currentCents),
            'progress_percentage' => $progressPercentage,
            'is_achieved' => $isAchieved,
            'target_date' => $goal->target_date?->toDateString(),
            'days_remaining' => $this->daysRemaining($goal->target_date),
            'color' => $goal->color,
            'icon' => $goal->icon,
            'notes' => $goal->notes,
            'account' => [
                'id' => $goal->account->id,
                'name' => $goal->account->name,
                'color' => $goal->account->color,
            ],
            'created_at' => $goal->created_at,
            'updated_at' => $goal->updated_at,
        ];
    }

    /**
     * Positivo se o alvo está no futuro, negativo se já passou.
     */
    private function daysRemaining(?Carbon $targetDate): ?int
    {
        if ($targetDate === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($targetDate, false);
    }
}
