<?php

namespace App\Data\StatementImports;

use App\Http\Requests\StatementImports\StoreStatementImportRequest;

/**
 * Descreve como interpretar um CSV de extrato: cada banco exporta colunas em
 * ordem/posição diferente, então o mapeamento é informado pelo cliente a
 * cada importação em vez de assumido fixo. Colunas são sempre endereçadas
 * pela posição (0-indexed), independente de haver cabeçalho ou não.
 */
final readonly class CsvImportMapping
{
    public function __construct(
        public int $dateColumn,
        public int $descriptionColumn,
        public int $amountColumn,
        public string $dateFormat,
        public string $delimiter,
        public bool $hasHeader,
    ) {}

    public static function fromRequest(StoreStatementImportRequest $request): self
    {
        return new self(
            dateColumn: (int) $request->integer('date_column'),
            descriptionColumn: (int) $request->integer('description_column'),
            amountColumn: (int) $request->integer('amount_column'),
            dateFormat: $request->string('date_format')->toString(),
            delimiter: $request->filled('delimiter') ? $request->string('delimiter')->toString() : ',',
            hasHeader: $request->boolean('has_header', true),
        );
    }
}
