<?php

namespace App\Services\Insights;

use App\Enum\AccountType;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Repositories\AccountRepository;
use App\Repositories\RecurrenceRepository;
use App\Repositories\TransactionGroupRepository;
use App\Repositories\TransactionRepository;
use App\Services\AccountBalanceService;
use App\Support\RecurrenceDateResolver;
use Illuminate\Support\Carbon;

/**
 * Projeta os próximos meses de fluxo de caixa combinando três fontes já
 * conhecidas — nada aqui depende de adivinhação, só de compromissos que já
 * existem no sistema:
 *
 * 1. Transações avulsas pendentes com vencimento futuro (excluindo as
 *    agrupadas numa fatura de cartão — essas entram pela fonte 2).
 * 2. Faturas de cartão ainda não pagas com vencimento futuro.
 * 3. Recorrências ativas: como o job de geração (RecurrenceService) só
 *    materializa transações reais dentro de uma janela curta
 *    (config('finance.recurrences.generation_days')), o restante da janela
 *    de projeção é simulado aqui reaplicando RecurrenceDateResolver::next()
 *    a partir de `next_due_date` — sem criar nenhuma transação real, é
 *    puramente uma leitura.
 *
 * O saldo inicial é a soma do saldo atual das contas não-cartão (mesmo
 * critério do "Saldo Consolidado" do Dashboard); cada mês projetado soma seu
 * próprio net (receita − despesa) ao saldo acumulado do mês anterior.
 */
final class CashFlowForecastService
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly TransactionGroupRepository $transactionGroupRepository,
        private readonly RecurrenceRepository $recurrenceRepository,
        private readonly AccountRepository $accountRepository,
        private readonly AccountBalanceService $accountBalanceService,
    ) {}

    /**
     * @return list<array{
     *     month: string,
     *     month_label: string,
     *     income_cents: int,
     *     expense_cents: int,
     *     net_cents: int,
     *     projected_balance_cents: int,
     * }>
     */
    public function project(int $userId, Carbon $referenceDate, int $months): array
    {
        $start = $referenceDate->copy()->startOfMonth();
        $end = $start->copy()->addMonthsNoOverflow($months - 1)->endOfMonth();

        $buckets = $this->emptyBuckets($start, $end);
        $this->addPendingTransactions($buckets, $userId, $start, $end);
        $this->addUnpaidInvoices($buckets, $userId, $start, $end);
        $this->addRecurrenceProjections($buckets, $userId, $start, $end);

        return $this->chainBalances($buckets, $this->currentNonCardBalanceCents($userId));
    }

    /** @return array<string, array{month: string, month_label: string, income_cents: int, expense_cents: int}> */
    private function emptyBuckets(Carbon $start, Carbon $end): array
    {
        $buckets = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $buckets[$key] = [
                'month' => $key,
                'month_label' => $cursor->translatedFormat('F/Y'),
                'income_cents' => 0,
                'expense_cents' => 0,
            ];
            $cursor->addMonthNoOverflow();
        }

        return $buckets;
    }

    private function addPendingTransactions(array &$buckets, int $userId, Carbon $start, Carbon $end): void
    {
        $rows = $this->transactionRepository->sumPendingUngroupedByMonth($userId, $start, $end);

        foreach ($rows as $row) {
            if (! isset($buckets[$row->month])) {
                continue;
            }

            // $row vem de Transaction::query()->get(), então $row->type já é
            // hidratado como TransactionType (cast do model), não a string
            // crua — comparar com o value teria sido sempre falso aqui.
            $key = $row->type === TransactionType::INCOME ? 'income_cents' : 'expense_cents';
            $buckets[$row->month][$key] += (int) $row->total_cents;
        }
    }

    private function addUnpaidInvoices(array &$buckets, int $userId, Carbon $start, Carbon $end): void
    {
        $groups = $this->transactionGroupRepository->listUnpaidWithDueBetween($userId, $start, $end);

        foreach ($groups as $group) {
            $key = $group->due_date->format('Y-m');

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['expense_cents'] += (int) $group->transactions->sum('amount_cents');
        }
    }

    private function addRecurrenceProjections(array &$buckets, int $userId, Carbon $start, Carbon $end): void
    {
        $recurrences = $this->recurrenceRepository->listActiveForUser($userId);

        foreach ($recurrences as $recurrence) {
            $occurrence = $recurrence->next_due_date->copy();

            while ($occurrence->lte($end) && ($recurrence->end_date === null || $occurrence->lte($recurrence->end_date))) {
                if ($occurrence->gte($start)) {
                    $key = $occurrence->format('Y-m');

                    if (isset($buckets[$key])) {
                        $bucketKey = $recurrence->type === TransactionType::INCOME ? 'income_cents' : 'expense_cents';
                        $buckets[$key][$bucketKey] += $recurrence->amount_cents;
                    }
                }

                $occurrence = RecurrenceDateResolver::next($recurrence->frequency, $recurrence->start_date, $occurrence, $recurrence->interval);
            }
        }
    }

    /**
     * @param  array<string, array{month: string, month_label: string, income_cents: int, expense_cents: int}>  $buckets
     * @return list<array{month: string, month_label: string, income_cents: int, expense_cents: int, net_cents: int, projected_balance_cents: int}>
     */
    private function chainBalances(array $buckets, int $startingBalanceCents): array
    {
        $runningCents = $startingBalanceCents;
        $result = [];

        foreach ($buckets as $bucket) {
            $netCents = $bucket['income_cents'] - $bucket['expense_cents'];
            $runningCents += $netCents;

            $result[] = $bucket + [
                'net_cents' => $netCents,
                'projected_balance_cents' => $runningCents,
            ];
        }

        return $result;
    }

    private function currentNonCardBalanceCents(int $userId): int
    {
        return $this->accountRepository->listForUser($userId)
            ->filter(fn (Account $account): bool => $account->type !== AccountType::CREDIT_CARD)
            ->sum(fn (Account $account): int => $this->accountBalanceService->calculateCurrentBalance($account)->cents);
    }
}
