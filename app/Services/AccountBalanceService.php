<?php

namespace App\Services;

use App\Common\Money;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Models\Account;

/**
 * Calcula o saldo atual de uma conta sob demanda, em vez de manter um saldo
 * em cache na tabela accounts (evitaria dessincronizar do histórico real de
 * lançamentos pagos).
 */
final class AccountBalanceService
{
    public function calculateCurrentBalance(Account $account): Money
    {
        $paidIncomeCents = (int) $account->transactions()
            ->where('status', TransactionStatus::PAID->value)
            ->where('type', TransactionType::INCOME->value)
            ->sum('amount_cents');

        $paidExpenseCents = (int) $account->transactions()
            ->where('status', TransactionStatus::PAID->value)
            ->where('type', TransactionType::EXPENSE->value)
            ->sum('amount_cents');

        return Money::fromCents($account->initial_balance_cents)
            ->add(Money::fromCents($paidIncomeCents))
            ->subtract(Money::fromCents($paidExpenseCents));
    }
}
