<?php

namespace App\Http\Resources\Assistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * As "actions" propostas pela IA já chegam aqui como arrays totalmente
 * montados (um por tipo: conta, transação, parcela) — heterogêneos de
 * propósito, é uma prévia pra confirmação do usuário, não uma entidade de
 * domínio única. Por isso passam direto, sem um Resource por item.
 *
 * @property array{clarification: ?string, actions: array} $resource
 */
class QuickAddResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'clarification' => $this->resource['clarification'],
            'actions' => $this->resource['actions'],
        ];
    }
}
