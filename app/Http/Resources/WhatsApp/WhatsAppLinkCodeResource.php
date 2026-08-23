<?php

namespace App\Http\Resources\WhatsApp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{code: string, expires_at: string} $resource
 */
class WhatsAppLinkCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource['code'],
            'expires_at' => $this->resource['expires_at'],
        ];
    }
}
