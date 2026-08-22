<?php

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{current: array, previous: array, delta: array, top_categories: array, others_cents: int} $resource
 */
class SpendingSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'current' => $this->resource['current'],
            'previous' => $this->resource['previous'],
            'delta' => $this->resource['delta'],
            'top_categories' => $this->resource['top_categories'],
            'others_cents' => $this->resource['others_cents'],
        ];
    }
}
