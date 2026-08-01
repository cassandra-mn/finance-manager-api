<?php

namespace App\Data\OpenFinance;

/**
 * Recorte do objeto "account" retornado pela API da Pluggy
 * (GET /accounts?itemId=...), apenas com os campos usados na sincronização.
 */
final readonly class PluggyAccountPayload
{
    public function __construct(
        public string $externalId,
        public string $type,
        public ?string $subtype,
        public string $name,
    ) {}

    /** @param  array<string, mixed>  $raw */
    public static function fromArray(array $raw): self
    {
        return new self(
            externalId: (string) $raw['id'],
            type: (string) ($raw['type'] ?? ''),
            subtype: isset($raw['subtype']) ? (string) $raw['subtype'] : null,
            name: (string) ($raw['name'] ?? ''),
        );
    }
}
