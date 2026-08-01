<?php

namespace App\Http\Resources\OpenFinance;

use App\Models\BankConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankConnection */
class BankConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Exposto de propósito: o frontend precisa do item id para pedir
            // um connect-token em modo reconexão quando status = login_error.
            'pluggy_item_id' => $this->pluggy_item_id,
            'institution_id' => $this->institution_id,
            'institution_name' => $this->institution_name,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'last_synced_at' => $this->last_synced_at,
            'last_sync_error' => $this->last_sync_error,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
