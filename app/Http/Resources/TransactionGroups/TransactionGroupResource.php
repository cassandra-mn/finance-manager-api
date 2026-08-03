<?php

namespace App\Http\Resources\TransactionGroups;

use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Transactions\TransactionResource;
use App\Models\TransactionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransactionGroup */
class TransactionGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account' => new AccountResource($this->whenLoaded('account')),
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'name' => $this->display_name,
            'reference_month' => $this->reference_month?->toDateString(),
            'closing_date' => $this->closing_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status,
            'display_status' => $this->display_status,
            'display_status_label' => $this->display_status->label(),
            'total_cents' => $this->total_cents,
            'transactions_count' => $this->whenCounted('transactions'),
            'payment_account' => new AccountResource($this->whenLoaded('paymentAccount')),
            'payment_transaction_id' => $this->payment_transaction_id,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
