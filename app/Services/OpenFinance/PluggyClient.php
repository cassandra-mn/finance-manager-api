<?php

namespace App\Services\OpenFinance;

use App\Exceptions\ServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente HTTP para a API da Pluggy (agregador Open Finance usado para
 * conectar contas bancárias e importar transações). A Pluggy não exige
 * mTLS/certificados — autentica com um apiKey de curta duração obtido via
 * clientId/clientSecret, que é cacheado para evitar reautenticar a cada
 * chamada.
 */
final class PluggyClient
{
    public function createConnectToken(?string $itemId = null): string
    {
        $payload = array_filter(['itemId' => $itemId], static fn (?string $value): bool => $value !== null);

        $response = $this->request()->post('/connect_token', $payload);

        if ($response->failed()) {
            Log::error('finance.open_finance.pluggy_connect_token_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ServiceException('Não foi possível iniciar a conexão com o banco.');
        }

        return (string) $response->json('accessToken');
    }

    /** @return array<string, mixed> */
    public function getItem(string $itemId): array
    {
        $response = $this->request()->get("/items/{$itemId}");

        if ($response->failed()) {
            Log::error('finance.open_finance.pluggy_get_item_failed', [
                'item_id' => $itemId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ServiceException('Não foi possível consultar a conexão bancária.');
        }

        return $response->json();
    }

    public function deleteItem(string $itemId): void
    {
        $response = $this->request()->delete("/items/{$itemId}");

        if ($response->failed() && $response->status() !== 404) {
            Log::error('finance.open_finance.pluggy_delete_item_failed', [
                'item_id' => $itemId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ServiceException('Não foi possível desconectar o banco.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listAccounts(string $itemId): array
    {
        $response = $this->request()->get('/accounts', ['itemId' => $itemId]);

        if ($response->failed()) {
            Log::error('finance.open_finance.pluggy_list_accounts_failed', [
                'item_id' => $itemId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ServiceException('Não foi possível buscar as contas do banco.');
        }

        return $response->json('results') ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function listAllTransactions(string $accountId, ?Carbon $from, ?Carbon $to): array
    {
        $transactions = [];
        $page = 1;

        do {
            $response = $this->request()->get('/transactions', array_filter([
                'accountId' => $accountId,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'page' => $page,
                'pageSize' => 500,
            ]));

            if ($response->failed()) {
                Log::error('finance.open_finance.pluggy_list_transactions_failed', [
                    'account_id' => $accountId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new ServiceException('Não foi possível buscar as transações do banco.');
            }

            $transactions = array_merge($transactions, $response->json('results') ?? []);
            $totalPages = (int) ($response->json('totalPages') ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $transactions;
    }

    private function apiKey(): string
    {
        return Cache::remember('open_finance.pluggy.api_key', now()->addMinutes(110), function (): string {
            $baseUrl = rtrim((string) config('open_finance.pluggy.base_url'), '/');

            try {
                $response = Http::timeout(10)
                    ->retry(2, 300, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false)
                    ->post("{$baseUrl}/auth", [
                        'clientId' => (string) config('open_finance.pluggy.client_id'),
                        'clientSecret' => (string) config('open_finance.pluggy.client_secret'),
                    ]);
            } catch (Throwable $e) {
                Log::error('finance.open_finance.pluggy_auth_failed', ['message' => $e->getMessage()]);

                throw new ServiceException('Não foi possível autenticar com o provedor de Open Finance.', previous: $e);
            }

            if ($response->failed()) {
                Log::error('finance.open_finance.pluggy_auth_failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new ServiceException('Não foi possível autenticar com o provedor de Open Finance.');
            }

            return (string) $response->json('apiKey');
        });
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('open_finance.pluggy.base_url'), '/');

        return Http::baseUrl($baseUrl)
            ->withHeaders(['X-API-KEY' => $this->apiKey()])
            ->timeout(20)
            ->retry(2, 300, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false);
    }
}
