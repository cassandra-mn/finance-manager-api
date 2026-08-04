<?php

namespace App\Http\Resources\Accounts;

use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Account */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): ?array
    {
        // Same reasoning as CategoryResource: Account uses SoftDeletes, so a
        // transaction/group whose account was soft-deleted still has a valid
        // account_id but the relation resolves to null. Laravel's resource
        // filter() happens to null this out for free when AccountResource is
        // nested inside another resource's toArray(), but that's an implicit
        // side effect we shouldn't rely on — guard explicitly so this is also
        // safe if ever resolved directly.
        if ($this->resource === null) {
            return null;
        }

        $currentBalance = app(AccountBalanceService::class)->calculateCurrentBalance($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'initial_balance_cents' => $this->initial_balance_cents,
            'credit_limit_cents' => $this->credit_limit_cents,
            'invoice_due_day' => $this->invoice_due_day,
            'invoice_closing_day' => $this->invoice_closing_day,
            'effective_invoice_closing_day' => $this->effective_invoice_closing_day,
            'current_balance_cents' => $currentBalance->cents,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
