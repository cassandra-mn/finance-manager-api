<?php

namespace App\Http\Resources\Assistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{answer: string} $resource
 */
class AskAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'answer' => $this->resource['answer'],
        ];
    }
}
