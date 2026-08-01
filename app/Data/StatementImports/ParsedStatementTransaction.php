<?php

namespace App\Data\StatementImports;

use App\Enum\TransactionType;
use Illuminate\Support\Carbon;

/**
 * Uma transação normalizada extraída de um arquivo de extrato (OFX ou CSV),
 * antes de virar um registro em `transactions`.
 */
final readonly class ParsedStatementTransaction
{
    public function __construct(
        public string $externalId,
        public string $description,
        public int $amountCents,
        public Carbon $date,
        public TransactionType $type,
    ) {}
}
