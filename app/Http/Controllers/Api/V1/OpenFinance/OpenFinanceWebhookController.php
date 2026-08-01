<?php

namespace App\Http\Controllers\Api\V1\OpenFinance;

use App\Http\Controllers\Controller;
use App\Jobs\OpenFinance\SyncBankConnectionJob;
use App\Repositories\BankConnectionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Recebe webhooks da Pluggy (item/updated, transactions/created, etc.). A
 * Pluggy não assina os webhooks por HMAC, então a autenticidade é garantida
 * por um segredo compartilhado na query string da URL cadastrada no
 * dashboard da Pluggy (?token=PLUGGY_WEBHOOK_SECRET). O corpo do webhook
 * nunca é usado além de extrair o itemId: a sincronização sempre rebusca o
 * estado real via GET /items/{itemId}, então um payload forjado no máximo
 * força uma sincronização a mais, nunca dados incorretos.
 */
class OpenFinanceWebhookController extends Controller
{
    public function __construct(
        private readonly BankConnectionRepository $repository,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('open_finance.pluggy.webhook_secret');
        $providedSecret = (string) $request->query('token', '');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json(['message' => 'Token inválido.'], Response::HTTP_UNAUTHORIZED);
        }

        $itemId = (string) $request->input('itemId', '');

        if ($itemId === '') {
            return response()->json(['message' => 'ok']);
        }

        $connection = $this->repository->findByPluggyItemId($itemId);

        if ($connection === null) {
            Log::info('finance.open_finance.webhook_unknown_item', ['item_id' => $itemId]);

            return response()->json(['message' => 'ok']);
        }

        SyncBankConnectionJob::dispatch($connection->id);

        return response()->json(['message' => 'ok']);
    }
}
