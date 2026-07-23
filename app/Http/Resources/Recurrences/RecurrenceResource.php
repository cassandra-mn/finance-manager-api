<?php

namespace App\Http\Resources\Recurrences;

use App\Http\Resources\Accounts\AccountResource;
use App\Http\Resources\Categories\CategoryResource;
use App\Models\Recurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Recurrence */
class RecurrenceResource extends JsonResource
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
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'frequency' => $this->frequency,
            'frequency_label' => $this->frequency->label(),
            'start_date' => $this->start_date?->toDateString(),
            'next_due_date' => $this->next_due_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
