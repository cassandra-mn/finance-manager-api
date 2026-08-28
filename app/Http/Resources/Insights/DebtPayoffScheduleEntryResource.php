<?php

namespace App\Http\Resources\Insights;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{month: int, starting_balance_cents: int, interest_cents: int, principal_cents: int, ending_balance_cents: int} $resource
 */
class DebtPayoffScheduleEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->resource['month'],
            'starting_balance_cents' => $this->resource['starting_balance_cents'],
            'interest_cents' => $this->resource['interest_cents'],
            'principal_cents' => $this->resource['principal_cents'],
            'ending_balance_cents' => $this->resource['ending_balance_cents'],
        ];
    }
}
