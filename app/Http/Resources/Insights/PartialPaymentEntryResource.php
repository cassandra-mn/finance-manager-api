<?php

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{month: string, month_label: string, shortfall_cents: int, count: int} $resource
 */
class PartialPaymentEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->resource['month'],
            'month_label' => $this->resource['month_label'],
            'shortfall_cents' => $this->resource['shortfall_cents'],
            'count' => $this->resource['count'],
        ];
    }
}
