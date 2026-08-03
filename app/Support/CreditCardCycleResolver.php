<?php

namespace App\Support;

use App\Models\Account;
use Illuminate\Support\Carbon;

/**
 * Determina em qual ciclo de fatura uma compra de cartão de crédito cai, a
 * partir do dia de fechamento e vencimento configurados na conta.
 *
 * Regra: uma compra até o dia de fechamento (inclusive) entra na fatura que
 * fecha no mês da compra; depois disso, entra na fatura que fecha no mês
 * seguinte. O vencimento da fatura fica no mesmo mês do fechamento quando
 * due_day >= closing_day, ou no mês seguinte quando due_day < closing_day
 * (caso comum: fecha perto do fim do mês, vence no começo do mês seguinte).
 * reference_month é o mês/ano do vencimento — é o que corresponde ao "compra
 * em julho vai para a fatura de agosto" do pedido original.
 *
 * Esta é uma primeira aproximação da regra real de cada operadora/banco; se
 * precisar de ajuste fino depois, o ponto de mudança é só esta classe.
 */
final class CreditCardCycleResolver
{
    public static function resolve(Account $account, Carbon $purchaseDate): CreditCardInvoiceCycle
    {
        $dueDay = (int) $account->invoice_due_day;
        $closingDay = (int) $account->effective_invoice_closing_day;

        $purchaseDay = $purchaseDate->copy()->startOfDay();
        $closingThisMonth = self::dateForDay($purchaseDay, $closingDay);

        $closingDate = $purchaseDay->lte($closingThisMonth)
            ? $closingThisMonth
            : self::dateForDay($closingThisMonth->copy()->addMonthNoOverflow(), $closingDay);

        $dueMonthAnchor = $dueDay >= $closingDay
            ? $closingDate
            : $closingDate->copy()->addMonthNoOverflow();

        $dueDate = self::dateForDay($dueMonthAnchor, $dueDay);

        return new CreditCardInvoiceCycle(
            referenceMonth: $dueDate->copy()->startOfMonth(),
            closingDate: $closingDate,
            dueDate: $dueDate,
        );
    }

    private static function dateForDay(Carbon $monthAnchor, int $day): Carbon
    {
        $start = $monthAnchor->copy()->startOfMonth();

        return $start->day(min($day, $start->daysInMonth));
    }
}
