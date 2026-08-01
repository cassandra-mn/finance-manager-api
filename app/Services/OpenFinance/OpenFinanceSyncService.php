<?php

namespace App\Services\OpenFinance;

use App\Data\OpenFinance\PluggyAccountPayload;
use App\Data\OpenFinance\PluggyTransactionPayload;
use App\Data\OpenFinance\SyncSummary;
use App\Enum\AccountType;
use App\Enum\BankConnectionStatus;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionOrigin;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\BankConnection;
use App\Repositories\AccountRepository;
use App\Repositories\BankConnectionRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Busca, para uma conexão bancária, o estado atual do item na Pluggy e
 * importa suas contas/transações. Transações são identificadas por
 * (account_id, external_id) — reexecutar a sincronização nunca duplica
 * lançamentos (ver migration transactions_account_external_id_unique).
 *
 * Toda transação importada entra sempre como `status = paid`: representa um
 * lançamento bancário já efetivado, diferente do fluxo pending/paid usado
 * para lançamentos manuais. Transações ainda `PENDING` do lado do banco são
 * ignoradas até serem efetivadas pela Pluggy numa sincronização futura.
 */
final class OpenFinanceSyncService
{
    public function __construct(
        private readonly PluggyClient $pluggyClient,
        private readonly BankConnectionRepository $bankConnectionRepository,
        private readonly AccountRepository $accountRepository,
        private readonly TransactionRepository $transactionRepository,
    ) {}

    public function sync(BankConnection $connection): SyncSummary
    {
        $summary = new SyncSummary;

        try {
            $item = $this->pluggyClient->getItem($connection->pluggy_item_id);
            $status = $this->mapStatus($item['status'] ?? null);
            $connection->status = $status;

            if ($connection->institution_name === null) {
                $connection->institution_id = $item['connector']['id'] ?? $connection->institution_id;
                $connection->institution_name = $item['connector']['name'] ?? $connection->institution_name;
            }

            if (in_array($status, [BankConnectionStatus::WAITING_USER_INPUT, BankConnectionStatus::LOGIN_ERROR], true)) {
                $connection->last_sync_error = $status === BankConnectionStatus::LOGIN_ERROR
                    ? 'As credenciais do banco expiraram, é necessário reconectar.'
                    : 'A instituição está aguardando uma ação adicional do usuário.';
                $this->bankConnectionRepository->save($connection);

                return $summary;
            }

            $this->syncAccountsAndTransactions($connection, $summary);

            $connection->last_synced_at = now();
            $connection->last_sync_error = $status === BankConnectionStatus::ERROR
                ? (string) ($item['error']['message'] ?? 'Erro desconhecido reportado pelo banco.')
                : null;
            $this->bankConnectionRepository->save($connection);

            return $summary;
        } catch (Throwable $e) {
            Log::error('finance.open_finance.sync_failed', [
                'bank_connection_id' => $connection->id,
                'message' => $e->getMessage(),
            ]);

            $connection->last_sync_error = $e->getMessage();
            $this->bankConnectionRepository->save($connection);

            throw $e;
        }
    }

    private function syncAccountsAndTransactions(BankConnection $connection, SyncSummary $summary): void
    {
        $accountsRaw = $this->pluggyClient->listAccounts($connection->pluggy_item_id);
        $lookbackDays = (int) config('open_finance.sync.transactions_lookback_days');
        $from = $connection->last_synced_at?->copy()->subDays(3) ?? now()->subDays($lookbackDays);

        foreach ($accountsRaw as $rawAccount) {
            $accountPayload = PluggyAccountPayload::fromArray($rawAccount);
            $account = $this->resolveAccount($connection, $accountPayload);

            if ($account === null) {
                continue;
            }

            $summary->accountsSynced++;

            $transactionsRaw = $this->pluggyClient->listAllTransactions($accountPayload->externalId, $from, now());

            foreach ($transactionsRaw as $rawTransaction) {
                $transactionPayload = PluggyTransactionPayload::fromArray($rawTransaction);

                if ($transactionPayload->isPending()) {
                    continue;
                }

                if ($this->importTransaction($connection, $account, $transactionPayload)) {
                    $summary->transactionsCreated++;
                } else {
                    $summary->transactionsSkipped++;
                }
            }
        }
    }

    /**
     * Resolve a conta local correspondente a uma conta da Pluggy, criando-a
     * se ainda não existir. Retorna null quando a conta já foi excluída
     * localmente pelo usuário — nesse caso ela nunca é recriada.
     */
    private function resolveAccount(BankConnection $connection, PluggyAccountPayload $payload): ?Account
    {
        $existing = $this->accountRepository->findByExternalAccount($connection->id, $payload->externalId);

        if ($existing !== null) {
            return $existing->trashed() ? null : $existing;
        }

        return $this->accountRepository->createFromExternal([
            'user_id' => $connection->user_id,
            'bank_connection_id' => $connection->id,
            'external_account_id' => $payload->externalId,
            'name' => $payload->name !== '' ? $payload->name : ($connection->institution_name ?? 'Conta bancária'),
            'type' => $this->mapAccountType($payload->type, $payload->subtype),
            'initial_balance_cents' => 0,
            'is_active' => true,
        ]);
    }

    private function importTransaction(BankConnection $connection, Account $account, PluggyTransactionPayload $payload): bool
    {
        try {
            DB::transaction(function () use ($connection, $account, $payload): void {
                $this->transactionRepository->createFromExternal([
                    'user_id' => $connection->user_id,
                    'account_id' => $account->id,
                    'category_id' => null,
                    'recurrence_id' => null,
                    'external_id' => $payload->externalId,
                    'origin' => TransactionOrigin::OPEN_FINANCE,
                    'type' => $payload->isCredit() ? TransactionType::INCOME : TransactionType::EXPENSE,
                    'entry_type' => TransactionEntryType::SINGLE,
                    'status' => TransactionStatus::PAID,
                    'description' => $payload->description !== '' ? $payload->description : 'Transação importada',
                    'amount_cents' => (int) round(abs($payload->amount) * 100),
                    'due_date' => $payload->date->toDateString(),
                    'paid_at' => $payload->date,
                    'notes' => null,
                ]);
            });

            return true;
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'transactions_account_external_id_unique')) {
                throw $e;
            }

            Log::warning('finance.open_finance.duplicate_transaction_prevented', [
                'account_id' => $account->id,
                'external_id' => $payload->externalId,
            ]);

            return false;
        }
    }

    private function mapAccountType(string $type, ?string $subtype): AccountType
    {
        return match (true) {
            strtoupper($type) === 'CREDIT' => AccountType::CREDIT_CARD,
            strtoupper((string) $subtype) === 'SAVINGS_ACCOUNT' => AccountType::SAVINGS,
            default => AccountType::CHECKING,
        };
    }

    private function mapStatus(?string $pluggyStatus): BankConnectionStatus
    {
        return match (strtoupper((string) $pluggyStatus)) {
            'UPDATED' => BankConnectionStatus::UPDATED,
            'UPDATING' => BankConnectionStatus::UPDATING,
            'LOGIN_ERROR' => BankConnectionStatus::LOGIN_ERROR,
            'OUTDATED' => BankConnectionStatus::OUTDATED,
            'WAITING_USER_INPUT' => BankConnectionStatus::WAITING_USER_INPUT,
            default => BankConnectionStatus::ERROR,
        };
    }
}
