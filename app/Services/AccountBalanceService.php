<?php

namespace App\Services;

use App\Common\Money;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Calcula o saldo atual de uma conta sob demanda, em vez de manter um saldo
 * em cache na tabela accounts (evitaria dessincronizar do histórico real de
 * lançamentos pagos).
 */
final class AccountBalanceService
{
    /**
     * $asOf reconstrói o saldo como estava ao final daquele dia (usado por
     * NetWorthHistoryService pra montar a série histórica) — sem ele,
     * calcula o saldo atual de verdade. Baseado em paid_at, que é quando o
     * dinheiro realmente mudou de mãos (não due_date, que é só a data
     * combinada do lançamento).
     */
    public function calculateCurrentBalance(Account $account, ?Carbon $asOf = null): Money
    {
        $paidIncomeCents = (int) $account->transactions()
            ->where('status', TransactionStatus::PAID->value)
            ->where('type', TransactionType::INCOME->value)
            ->when($asOf, fn (Builder $query) => $query->where('paid_at', '<=', $asOf))
            ->sum('amount_cents');

        $paidExpenseCents = (int) $account->transactions()
            ->where('status', TransactionStatus::PAID->value)
            ->where('type', TransactionType::EXPENSE->value)
            ->when($asOf, fn (Builder $query) => $query->where('paid_at', '<=', $asOf))
            ->sum('amount_cents');

        // Pagamento parcial: só o valor efetivamente pago saiu da conta — o
        // restante (amount_cents - paid_amount_cents) fica de fora do saldo,
        // já que não é uma pendência rastreada pelo app (ver TransactionService
        // ::markAsPaid / PartialPaymentsService).
        $partiallyPaidIncomeCents = (int) $account->transactions()
            ->where('status', TransactionStatus::PARTIALLY_PAID->value)
            ->where('type', TransactionType::INCOME->value)
            ->when($asOf, fn (Builder $query) => $query->where('paid_at', '<=', $asOf))
            ->sum('paid_amount_cents');

        $partiallyPaidExpenseCents = (int) $account->transactions()
            ->where('status', TransactionStatus::PARTIALLY_PAID->value)
            ->where('type', TransactionType::EXPENSE->value)
            ->when($asOf, fn (Builder $query) => $query->where('paid_at', '<=', $asOf))
            ->sum('paid_amount_cents');

        return Money::fromCents($account->initial_balance_cents)
            ->add(Money::fromCents($paidIncomeCents + $partiallyPaidIncomeCents))
            ->subtract(Money::fromCents($paidExpenseCents + $partiallyPaidExpenseCents));
    }
}
