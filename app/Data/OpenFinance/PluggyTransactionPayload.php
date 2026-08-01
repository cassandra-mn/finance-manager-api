<?php

namespace App\Data\OpenFinance;

use Illuminate\Support\Carbon;

/**
 * Recorte do objeto "transaction" retornado pela API da Pluggy
 * (GET /transactions?accountId=...), apenas com os campos usados na
 * sincronização.
 */
final readonly class PluggyTransactionPayload
{
    public function __construct(
        public string $externalId,
        public string $description,
        public float $amount,
        public Carbon $date,
        public string $type,
        public string $status,
    ) {}

    /** @param  array<string, mixed>  $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            externalId: (string) $raw['id'],
            description: (string) ($raw['description'] ?? ''),
            amount: (float) ($raw['amount'] ?? 0),
            date: Carbon::parse((string) $raw['date']),
            type: (string) ($raw['type'] ?? ''),
            status: (string) ($raw['status'] ?? 'POSTED'),
        );
    }

    public function isPending(): bool
    {
        return strtoupper($this->status) === 'PENDING';
    }

    public function isCredit(): bool
    {
        return strtoupper($this->type) === 'CREDIT';
    }
}
