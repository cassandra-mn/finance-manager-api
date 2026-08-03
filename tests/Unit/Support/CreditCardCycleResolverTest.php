<?php

namespace Tests\Unit\Support;

use App\Models\Account;
use App\Support\CreditCardCycleResolver;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Casos aqui sempre definem invoice_closing_day explicitamente, para não
 * depender de config() (não disponível nestes testes unitários puros, sem
 * boot da aplicação). O cálculo do dia de fechamento padrão a partir do
 * offset é coberto em teste de Feature (Account::effective_invoice_closing_day).
 */
class CreditCardCycleResolverTest extends TestCase
{
    public function test_purchase_before_closing_day_belongs_to_the_current_month_cycle(): void
    {
        $account = $this->creditCard(dueDay: 15, closingDay: 5);

        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-07-03'));

        $this->assertSame('2026-07-05', $cycle->closingDate->toDateString());
        $this->assertSame('2026-07-15', $cycle->dueDate->toDateString());
        $this->assertSame('2026-07-01', $cycle->referenceMonth->toDateString());
    }

    public function test_purchase_on_the_closing_day_itself_belongs_to_the_current_month_cycle(): void
    {
        $account = $this->creditCard(dueDay: 15, closingDay: 5);

        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-07-05'));

        $this->assertSame('2026-07-05', $cycle->closingDate->toDateString());
        $this->assertSame('2026-07-15', $cycle->dueDate->toDateString());
    }

    public function test_purchase_after_closing_day_rolls_to_the_next_month_cycle(): void
    {
        $account = $this->creditCard(dueDay: 15, closingDay: 5);

        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-07-10'));

        $this->assertSame('2026-08-05', $cycle->closingDate->toDateString());
        $this->assertSame('2026-08-15', $cycle->dueDate->toDateString());
        $this->assertSame('2026-08-01', $cycle->referenceMonth->toDateString());
    }

    /**
     * Caso do pedido original: cartão fecha perto do fim do mês (25) e vence
     * no começo do mês seguinte (5) — uma compra em julho (antes do
     * fechamento) cai na "fatura de agosto".
     */
    public function test_purchase_in_july_falls_into_the_august_invoice_when_due_day_rolls_into_next_month(): void
    {
        $account = $this->creditCard(dueDay: 5, closingDay: 25);

        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-07-10'));

        $this->assertSame('2026-07-25', $cycle->closingDate->toDateString());
        $this->assertSame('2026-08-05', $cycle->dueDate->toDateString());
        $this->assertSame('2026-08-01', $cycle->referenceMonth->toDateString());
    }

    public function test_purchase_after_closing_with_rolling_due_date_falls_into_the_following_invoice(): void
    {
        $account = $this->creditCard(dueDay: 5, closingDay: 25);

        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-07-30'));

        $this->assertSame('2026-08-25', $cycle->closingDate->toDateString());
        $this->assertSame('2026-09-05', $cycle->dueDate->toDateString());
        $this->assertSame('2026-09-01', $cycle->referenceMonth->toDateString());
    }

    public function test_due_day_31_clamps_to_the_last_day_of_shorter_months(): void
    {
        $account = $this->creditCard(dueDay: 31, closingDay: 28);

        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-02-27'));

        $this->assertSame('2026-02-28', $cycle->closingDate->toDateString());
        $this->assertSame('2026-02-28', $cycle->dueDate->toDateString());
    }

    public function test_rolling_due_date_also_clamps_to_the_last_day_of_shorter_months(): void
    {
        $account = $this->creditCard(dueDay: 31, closingDay: 25);

        // Fecha em 25/01, vencimento cairia em 31/02 -> clampa para 28/02 (2026 não é bissexto).
        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-01-28'));

        $this->assertSame('2026-02-25', $cycle->closingDate->toDateString());
        $this->assertSame('2026-02-28', $cycle->dueDate->toDateString());
    }

    public function test_purchase_in_december_rolls_the_cycle_into_the_next_year(): void
    {
        $account = $this->creditCard(dueDay: 5, closingDay: 25);

        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-12-10'));

        $this->assertSame('2026-12-25', $cycle->closingDate->toDateString());
        $this->assertSame('2027-01-05', $cycle->dueDate->toDateString());
        $this->assertSame('2027-01-01', $cycle->referenceMonth->toDateString());
    }

    public function test_purchase_after_closing_in_december_rolls_two_months_into_the_next_year(): void
    {
        $account = $this->creditCard(dueDay: 5, closingDay: 25);

        $cycle = CreditCardCycleResolver::resolve($account, Carbon::parse('2026-12-28'));

        $this->assertSame('2027-01-25', $cycle->closingDate->toDateString());
        $this->assertSame('2027-02-05', $cycle->dueDate->toDateString());
        $this->assertSame('2027-02-01', $cycle->referenceMonth->toDateString());
    }

    private function creditCard(int $dueDay, int $closingDay): Account
    {
        return new Account([
            'invoice_due_day' => $dueDay,
            'invoice_closing_day' => $closingDay,
        ]);
    }
}
