<?php

namespace App\Services\Assistant\Commands;

use App\Enum\TransactionStatus;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\TransactionRepository;
use RuntimeException;

final readonly class CreateTransactionCommand implements AssistantCommand
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, int>  $resolvedAccountIds  Mapa client_id => id de conta,
     *                                                  já resolvido pelas ações de criar conta processadas antes desta no mesmo lote.
     */
    public function __construct(
        private AccountRepository $accountRepository,
        private TransactionRepository $transactionRepository,
        private string $summary,
        private array $payload,
        private ?string $accountRef,
        private array $resolvedAccountIds,
    ) {}

    public function execute(User $user): array
    {
        $transaction = $this->transactionRepository->create(array_merge(
            $this->payload,
            [
                'user_id' => $user->id,
                'account_id' => $this->resolveAccountId($user),
                'status' => TransactionStatus::PENDING,
            ],
        ));

        return ['kind' => 'transaction', 'summary' => $this->summary, 'id' => $transaction->id];
    }

    private function resolveAccountId(User $user): int
    {
        $accountId = $this->payload['account_id'] ?? null;

        if ($accountId === null) {
            if ($this->accountRef === null || ! isset($this->resolvedAccountIds[$this->accountRef])) {
                throw new RuntimeException('Ação de transação sem account_id/account_ref resolvível.');
            }

            return $this->resolvedAccountIds[$this->accountRef];
        }

        if (! $this->accountRepository->existsActiveForUser($user->id, (int) $accountId)) {
            throw new RuntimeException("Conta {$accountId} não existe mais ou foi desativada.");
        }

        return (int) $accountId;
    }
}
