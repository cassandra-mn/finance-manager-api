<?php

namespace App\Http\Resources\Accounts;

use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Account */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentBalance = app(AccountBalanceService::class)->calculateCurrentBalance($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'initial_balance_cents' => $this->initial_balance_cents,
            'current_balance_cents' => $currentBalance->cents,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
