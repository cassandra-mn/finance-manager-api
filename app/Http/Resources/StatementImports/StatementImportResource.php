<?php

namespace App\Http\Resources\StatementImports;

use App\Models\StatementImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StatementImport */
class StatementImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'format' => $this->format,
            'format_label' => $this->format->label(),
            'original_filename' => $this->original_filename,
            'transactions_created' => $this->transactions_created,
            'transactions_skipped' => $this->transactions_skipped,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
