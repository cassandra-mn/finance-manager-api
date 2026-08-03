<?php

namespace App\Console\Commands;

use App\Services\TransactionGroupService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class CloseDueCreditCardInvoicesCommand extends Command
{
    protected $signature = 'finance:close-due-credit-card-invoices
        {--date= : Data de referência (YYYY-MM-DD) para o fechamento. Padrão: hoje.}';

    protected $description = 'Fecha as faturas de cartão de crédito abertas cujo dia de fechamento já chegou.';

    public function handle(TransactionGroupService $service): int
    {
        $referenceDate = $this->option('date') ? Carbon::parse($this->option('date')) : null;

        try {
            $summary = $service->closeDueInvoices($referenceDate);
        } catch (Throwable $e) {
            $this->error("Falha inesperada ao fechar faturas de cartão: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Fechamento de faturas de cartão concluído.');
        $this->table(
            ['Faturas fechadas', 'Erros'],
            [[$summary->invoicesClosed, $summary->errors]],
        );

        return self::SUCCESS;
    }
}
