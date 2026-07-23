<?php

namespace App\Data\Recurrences;

/**
 * Resultado agregado de uma execução de geração de ocorrências. `skipped`
 * cobre tanto ocorrências já existentes (idempotência) quanto ocorrências
 * não geradas por conta/categoria inválida; `errors` cobre falhas
 * inesperadas que não impedem o processamento das demais regras.
 */
final class GenerateRecurringTransactionsSummary
{
    public int $recurrencesProcessed = 0;

    public int $transactionsCreated = 0;

    public int $occurrencesSkipped = 0;

    public int $errors = 0;
}
