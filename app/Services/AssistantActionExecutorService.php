<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\User;
use App\Repositories\AccountRepository;
use App\Repositories\TransactionRepository;
use App\Services\Assistant\Commands\AssistantCommand;
use App\Services\Assistant\Commands\CreateAccountCommand;
use App\Services\Assistant\Commands\CreateTransactionCommand;
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
 *
 * Cada ação vira um AssistantCommand (Command pattern) antes de ser
 * executada — execute() abaixo só orquestra o lote (resolve o comando certo
 * pra cada ação, mantém o mapa de contas recém-criadas, chama execute() de
 * cada um), sem saber o que cada tipo de ação faz por dentro. As ações
 * continuam chegando/saindo como arrays com tag (não como os próprios
 * objetos de Command): esse formato é o que persiste em
 * WhatsAppSession::context (coluna JSON) entre a proposta e a confirmação
 * do usuário, e objetos não sobrevivem a esse round-trip por JSON.
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
                    $command = $this->buildCommand($action, $resolvedAccountIds);
                    $result = $command->execute($user);
                    $results[] = $result;

                    if ($action['kind'] === 'account') {
                        $resolvedAccountIds[$action['client_id']] = $result['id'];
                    }
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

    /**
     * @param  array<string, mixed>  $action
     * @param  array<string, int>  $resolvedAccountIds
     */
    private function buildCommand(array $action, array $resolvedAccountIds): AssistantCommand
    {
        return match ($action['kind']) {
            'account' => new CreateAccountCommand(
                $this->accountRepository,
                $action['summary'],
                $action['payload'],
            ),
            'transaction' => new CreateTransactionCommand(
                $this->accountRepository,
                $this->transactionRepository,
                $action['summary'],
                $action['payload'],
                $action['account_ref'] ?? null,
                $resolvedAccountIds,
            ),
            default => throw new RuntimeException("Tipo de ação desconhecido: \"{$action['kind']}\"."),
        };
    }
}
