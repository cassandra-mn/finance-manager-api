<?php

namespace App\Jobs\OpenFinance;

use App\Models\BankConnection;
use App\Services\OpenFinance\OpenFinanceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Ponto único de disparo da sincronização de uma conexão bancária — usado
 * pela criação da conexão, o resync manual, o webhook da Pluggy e o comando
 * agendado. Recebe o id (não o model) para evitar reidratar um estado
 * potencialmente desatualizado quando o job é processado.
 */
class SyncBankConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $bankConnectionId,
    ) {}

    public function handle(OpenFinanceSyncService $service): void
    {
        $connection = BankConnection::query()->withTrashed()->find($this->bankConnectionId);

        if ($connection === null || $connection->trashed()) {
            return;
        }

        $service->sync($connection);
    }
}
