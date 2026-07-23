<?php

namespace App\Http\Resources\Budgets;

use App\Http\Resources\Categories\CategoryResource;
use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Budget */
class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'amount_cents' => $this->amount_cents,
            'reference_month' => $this->reference_month,
            'reference_year' => $this->reference_year,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
