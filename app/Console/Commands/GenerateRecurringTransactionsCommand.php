<?php

namespace App\Console\Commands;

use App\Services\RecurrenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class GenerateRecurringTransactionsCommand extends Command
{
    protected $signature = 'finance:generate-recurring-transactions
        {--date= : Data de referência (YYYY-MM-DD) para a janela de geração. Padrão: hoje.}';

    protected $description = 'Gera as transações pendentes de recorrências ativas dentro da janela de geração configurada.';

    public function handle(RecurrenceService $service): int
    {
        $referenceDate = $this->option('date') ? Carbon::parse($this->option('date')) : null;

        try {
            $summary = $service->generateOccurrences($referenceDate);
        } catch (Throwable $e) {
            $this->error("Falha inesperada ao gerar transações recorrentes: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Geração de transações recorrentes concluída.');
        $this->table(
            ['Regras processadas', 'Transações criadas', 'Ignoradas', 'Erros'],
            [[$summary->recurrencesProcessed, $summary->transactionsCreated, $summary->occurrencesSkipped, $summary->errors]],
        );

        return self::SUCCESS;
    }
}
