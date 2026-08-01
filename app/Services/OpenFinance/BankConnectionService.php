<?php

namespace App\Services\OpenFinance;

use App\Data\OpenFinance\CreateBankConnectionData;
use App\Enum\BankConnectionStatus;
use App\Exceptions\ServiceException;
use App\Http\Requests\OpenFinance\CreateConnectTokenRequest;
use App\Http\Requests\OpenFinance\StoreBankConnectionRequest;
use App\Jobs\OpenFinance\SyncBankConnectionJob;
use App\Models\BankConnection;
use App\Repositories\BankConnectionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class BankConnectionService
{
    public function __construct(
        private readonly PluggyClient $pluggyClient,
        private readonly BankConnectionRepository $repository,
    ) {}

    /** @return Collection<int, BankConnection> */
    public function listForUser(int $userId): Collection
    {
        return $this->repository->listForUser($userId);
    }

    public function createConnectToken(CreateConnectTokenRequest $request): string
    {
        $itemId = $request->filled('item_id') ? $request->string('item_id')->toString() : null;

        return $this->pluggyClient->createConnectToken($itemId);
    }

    public function connect(StoreBankConnectionRequest $request): BankConnection
    {
        $data = CreateBankConnectionData::fromRequest($request, $request->user()->id);

        try {
            $connection = $this->repository->create([
                'user_id' => $data->userId,
                'pluggy_item_id' => $data->pluggyItemId,
                'status' => BankConnectionStatus::UPDATING,
            ]);
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'bank_connections_pluggy_item_id_unique')) {
                Log::error('finance.open_finance.connection_create_failed', [
                    'user_id' => $data->userId,
                    'message' => $e->getMessage(),
                ]);

                throw new ServiceException('Não foi possível conectar ao banco.', previous: $e);
            }

            throw ValidationException::withMessages([
                'item_id' => ['Esta conexão já foi registrada.'],
            ]);
        } catch (Throwable $e) {
            Log::error('finance.open_finance.connection_create_failed', [
                'user_id' => $data->userId,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível conectar ao banco.', previous: $e);
        }

        SyncBankConnectionJob::dispatch($connection->id);

        // Com a queue `sync` (padrão em testes/local), o job acima já rodou
        // de forma síncrona neste ponto — refresh() reflete o resultado
        // imediato da sincronização. Com a queue `database` (produção), o
        // job ainda não rodou e o refresh() é um no-op inofensivo.
        return $connection->refresh();
    }

    public function resync(BankConnection $connection): BankConnection
    {
        SyncBankConnectionJob::dispatch($connection->id);

        return $connection->refresh();
    }

    public function disconnect(BankConnection $connection): void
    {
        $this->pluggyClient->deleteItem($connection->pluggy_item_id);

        try {
            $this->repository->delete($connection);
        } catch (Throwable $e) {
            Log::error('finance.open_finance.disconnect_failed', [
                'bank_connection_id' => $connection->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível desconectar o banco.', previous: $e);
        }
    }
}
