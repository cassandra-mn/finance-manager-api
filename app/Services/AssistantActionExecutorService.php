<?php

namespace App\Services;

use App\Enum\TransactionStatus;
use App\Exceptions\ServiceException;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Persiste as ações já propostas e validadas por AiAssistantService::quickAdd()
 * depois que o usuário confirma. Mantido separado de AiAssistantService para
 * não misturar "interpretar texto livre" com "gravar no banco" — hoje só o
 * bot do WhatsApp usa isso, já que o frontend web executa as ações
 * confirmadas chamando os endpoints normais de accounts/transactions.
 */
final class AssistantActionExecutorService
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly TransactionRepository $transactionRepository,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return list<array{kind: string, summary: string, id: int}>
     */
    public function execute(array $actions, User $user): array
    {
        try {
            return DB::transaction(function () use ($actions, $user): array {
                $resolvedAccountIds = [];
                $results = [];

                foreach ($actions as $action) {
                    if ($action['kind'] === 'account') {
                        $account = $this->accountRepository->create(array_merge(
                            ['user_id' => $user->id],
                            $action['payload'],
                        ));

                        $resolvedAccountIds[$action['client_id']] = $account->id;
                        $results[] = ['kind' => 'account', 'summary' => $action['summary'], 'id' => $account->id];

                        continue;
                    }

                    $accountId = $this->resolveAccountId($action, $user, $resolvedAccountIds);

                    $transaction = $this->transactionRepository->create(array_merge(
                        $action['payload'],
                        [
                            'user_id' => $user->id,
                            'account_id' => $accountId,
                            'status' => TransactionStatus::PENDING,
                        ],
                    ));

                    $results[] = ['kind' => 'transaction', 'summary' => $action['summary'], 'id' => $transaction->id];
                }

                return $results;
            });
        } catch (Throwable $e) {
            Log::error('finance.whatsapp.action_execution_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível concluir o lançamento.', previous: $e);
        }
    }

    /** @param  array<string, int>  $resolvedAccountIds */
    private function resolveAccountId(array $action, User $user, array $resolvedAccountIds): int
    {
        $accountId = $action['payload']['account_id'] ?? null;

        if ($accountId === null) {
            $accountRef = $action['account_ref'] ?? null;

            if ($accountRef === null || ! isset($resolvedAccountIds[$accountRef])) {
                throw new RuntimeException('Ação de transação sem account_id/account_ref resolvível.');
            }

            return $resolvedAccountIds[$accountRef];
        }

        if (! $this->accountRepository->existsActiveForUser($user->id, (int) $accountId)) {
            throw new RuntimeException("Conta {$accountId} não existe mais ou foi desativada.");
        }

        return (int) $accountId;
    }
}
