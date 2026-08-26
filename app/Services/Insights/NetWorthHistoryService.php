<?php

namespace App\Services\Insights;

use App\Enum\AccountType;
use App\Models\Account;
use App\Repositories\AccountRepository;
use App\Services\AccountBalanceService;
use Illuminate\Support\Carbon;

/**
 * Reconstrói o patrimônio (soma do saldo das contas não-cartão) ao final de
 * cada um dos últimos N meses, sem precisar guardar snapshot nenhum — o
 * saldo de cada mês é recalculado sob demanda a partir do histórico real de
 * transações pagas (AccountBalanceService::calculateCurrentBalance com
 * $asOf), o mesmo princípio já usado pro saldo atual em toda a aplicação.
 */
final class NetWorthHistoryService
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly AccountBalanceService $accountBalanceService,
    ) {}

    /**
     * @return list<array{month: string, month_label: string, balance_cents: int}>
     */
    public function history(int $userId, Carbon $referenceDate, int $months): array
    {
        $accounts = $this->accountRepository->listForUser($userId)
            ->filter(fn (Account $account): bool => $account->type !== AccountType::CREDIT_CARD);

        $end = $referenceDate->copy()->endOfMonth();
        $start = $end->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();

        $result = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $monthEnd = $cursor->copy()->endOfMonth();

            // Uma conta criada depois desse mês não existia ainda — não
            // pode contribuir com o saldo inicial dela pro patrimônio de um
            // mês em que ela nem tinha sido cadastrada.
            $eligibleAccounts = $accounts->filter(
                fn (Account $account): bool => $account->created_at === null || $account->created_at->lte($monthEnd)
            );

            $balanceCents = $eligibleAccounts->sum(
                fn (Account $account): int => $this->accountBalanceService->calculateCurrentBalance($account, $monthEnd)->cents
            );

            $result[] = [
                'month' => $cursor->format('Y-m'),
                'month_label' => $cursor->translatedFormat('F/Y'),
                'balance_cents' => $balanceCents,
            ];

            $cursor->addMonthNoOverflow();
        }

        return $result;
    }
}
