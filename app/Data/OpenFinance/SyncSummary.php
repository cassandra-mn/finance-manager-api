<?php

namespace App\Data\OpenFinance;

/**
 * Resultado agregado de uma sincronização de conexão bancária.
 * `transactionsSkipped` cobre transações já importadas anteriormente
 * (idempotência) — não é um sinal de erro.
 */
final class SyncSummary
{
    public int $accountsSynced = 0;

    public int $transactionsCreated = 0;

    public int $transactionsSkipped = 0;
}
