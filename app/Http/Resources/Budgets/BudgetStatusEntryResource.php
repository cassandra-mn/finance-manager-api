<?php

namespace App\Http\Resources\Budgets;

use App\Enum\BudgetStatus;
use App\Http\Resources\Categories\CategoryResource;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formata uma entrada de status de orçamento (budget + gasto do período).
 * Compartilhada por BudgetController::status e
 * InsightsController::budgetProjection, que usam exatamente os mesmos campos
 * base — a projeção linear é só um adicional opcional dessa segunda tela.
 *
 * @property array{budget: Budget, spent_cents: int, remaining_cents: int, usage_percentage: float, status: BudgetStatus, projected_spent_cents?: int, projected_overrun_cents?: int, is_projected_to_exceed?: bool} $resource
 */
class BudgetStatusEntryResource extends JsonResource
{
    public function __construct($resource, private readonly bool $withProjection = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $budget = $this->resource['budget'];

        return [
            'id' => $budget->id,
            'category' => $budget->category ? new CategoryResource($budget->category) : null,
            'amount_cents' => $budget->amount_cents,
            'spent_cents' => $this->resource['spent_cents'],
            'remaining_cents' => $this->resource['remaining_cents'],
            'usage_percentage' => $this->resource['usage_percentage'],
            'status' => $this->resource['status']->value,
            'status_label' => $this->resource['status']->label(),
            'projected_spent_cents' => $this->when($this->withProjection, fn () => $this->resource['projected_spent_cents'] ?? null),
            'projected_overrun_cents' => $this->when($this->withProjection, fn () => $this->resource['projected_overrun_cents'] ?? null),
            'is_projected_to_exceed' => $this->when($this->withProjection, fn () => $this->resource['is_projected_to_exceed'] ?? null),
        ];
    }
}
