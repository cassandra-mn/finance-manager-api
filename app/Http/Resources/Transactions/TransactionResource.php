<?php

namespace App\Http\Resources\Transactions;

use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Categories\CategoryResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account' => new AccountResource($this->whenLoaded('account')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'entry_type' => $this->entry_type,
            'entry_type_label' => $this->entry_type->label(),
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
