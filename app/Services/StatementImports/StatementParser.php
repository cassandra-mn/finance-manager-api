<?php

namespace App\Services\StatementImports;

use App\Data\StatementImports\CsvImportMapping;
use App\Data\StatementImports\ParsedStatementTransaction;

/**
 * Contrato comum aos parsers de extrato (um por formato suportado). $mapping
 * só é usado pelo parser de CSV (cada banco exporta colunas em posição
 * diferente); os demais formatos o ignoram — mantido no contrato em vez de
 * variar a assinatura por implementação, pra StatementImportService poder
 * chamar qualquer parser da mesma forma.
 */
interface StatementParser
{
    /** @return array<int, ParsedStatementTransaction> */
    public function parse(string $contents, ?CsvImportMapping $mapping): array;
}
