<?php

namespace App\Console\Commands;

use App\Jobs\OpenFinance\SyncBankConnectionJob;
use App\Repositories\BankConnectionRepository;
use Illuminate\Console\Command;
use Throwable;

class SyncOpenFinanceConnectionsCommand extends Command
{
    protected $signature = 'open-finance:sync-connections';

    protected $description = 'Enfileira a sincronização de todas as conexões bancárias (Open Finance) ativas.';

    public function handle(BankConnectionRepository $repository): int
    {
        try {
            $connections = $repository->listSyncable();
        } catch (Throwable $e) {
            $this->error("Falha inesperada ao listar conexões bancárias: {$e->getMessage()}");

            return self::FAILURE;
        }

        foreach ($connections as $connection) {
            SyncBankConnectionJob::dispatch($connection->id);
        }

        $this->info('Sincronização de conexões bancárias enfileirada.');
        $this->table(['Conexões processadas'], [[$connections->count()]]);

        return self::SUCCESS;
    }
}
