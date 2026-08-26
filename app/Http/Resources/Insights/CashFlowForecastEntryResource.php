<?php

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{month: string, month_label: string, income_cents: int, expense_cents: int, net_cents: int, projected_balance_cents: int} $resource
 */
class CashFlowForecastEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->resource['month'],
            'month_label' => $this->resource['month_label'],
            'income_cents' => $this->resource['income_cents'],
            'expense_cents' => $this->resource['expense_cents'],
            'net_cents' => $this->resource['net_cents'],
            'projected_balance_cents' => $this->resource['projected_balance_cents'],
        ];
    }
}
