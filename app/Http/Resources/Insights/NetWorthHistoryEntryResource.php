<?php

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{month: string, month_label: string, balance_cents: int} $resource
 */
class NetWorthHistoryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->resource['month'],
            'month_label' => $this->resource['month_label'],
            'balance_cents' => $this->resource['balance_cents'],
        ];
    }
}
