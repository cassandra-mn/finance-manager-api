<?php

namespace App\Http\Resources\Insights;

use App\Models\Recurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{recurrence: Recurrence, monthly_equivalent_cents: int, annual_cost_cents: int} $resource
 */
class RecurringCommitmentEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Recurrence $recurrence */
        $recurrence = $this->resource['recurrence'];

        return [
            'id' => $recurrence->id,
            'description' => $recurrence->description,
            'type' => $recurrence->type->value,
            'frequency' => $recurrence->frequency->value,
            'frequency_label' => $recurrence->frequency->label(),
            'amount_cents' => $recurrence->amount_cents,
            'monthly_equivalent_cents' => $this->resource['monthly_equivalent_cents'],
            'annual_cost_cents' => $this->resource['annual_cost_cents'],
            'account' => [
                'id' => $recurrence->account->id,
                'name' => $recurrence->account->name,
                'color' => $recurrence->account->color,
            ],
            'category' => $recurrence->category ? [
                'id' => $recurrence->category->id,
                'name' => $recurrence->category->name,
                'color' => $recurrence->category->color,
                'icon' => $recurrence->category->icon,
            ] : null,
        ];
    }
}
