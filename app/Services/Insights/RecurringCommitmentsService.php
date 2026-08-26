<?php

namespace App\Services\Insights;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionType;
use App\Models\Recurrence;
use App\Repositories\RecurrenceRepository;

/**
 * Audita o compromisso recorrente do usuário: normaliza cada recorrência
 * ativa (independente da frequência) pra um custo mensal e anual
 * equivalente, pra responder "quanto eu tenho comprometido por mês" e
 * "quanto essa assinatura me custa por ano" — a mesma pergunta que apps como
 * Rocket Money respondem, aqui sem precisar de nenhum dado novo, só
 * normalizar o que já existe. O "e se eu cancelar X" é resolvido no
 * frontend, subtraindo o valor de uma linha do total — não precisa de
 * endpoint próprio.
 */
final class RecurringCommitmentsService
{
    public function __construct(
        private readonly RecurrenceRepository $recurrenceRepository,
    ) {}

    /**
     * @return list<array{
     *     recurrence: Recurrence,
     *     monthly_equivalent_cents: int,
     *     annual_cost_cents: int,
     * }>
     */
    public function list(int $userId): array
    {
        return $this->recurrenceRepository->listActiveForUser($userId)
            ->map(function (Recurrence $recurrence): array {
                $annualCostCents = $this->annualCostCents($recurrence);

                return [
                    'recurrence' => $recurrence,
                    'monthly_equivalent_cents' => intdiv($annualCostCents, 12),
                    'annual_cost_cents' => $annualCostCents,
                ];
            })
            ->values()
            ->all();
    }

    private function annualCostCents(Recurrence $recurrence): int
    {
        $occurrencesPerYear = match ($recurrence->frequency) {
            RecurrenceFrequency::WEEKLY => 52,
            RecurrenceFrequency::FORTNIGHTLY => 26,
            RecurrenceFrequency::MONTHLY => 12,
            RecurrenceFrequency::YEARLY => 1,
        };

        return intdiv($recurrence->amount_cents * $occurrencesPerYear, max(1, $recurrence->interval));
    }

    /**
     * @param  list<array{recurrence: Recurrence, monthly_equivalent_cents: int, annual_cost_cents: int}>  $entries
     * @return array{
     *     total_monthly_income_cents: int,
     *     total_monthly_expense_cents: int,
     *     total_annual_income_cents: int,
     *     total_annual_expense_cents: int,
     *     net_monthly_cents: int,
     *     count: int,
     * }
     */
    public function summarize(array $entries): array
    {
        $incomeEntries = array_filter($entries, fn (array $entry): bool => $entry['recurrence']->type === TransactionType::INCOME);
        $expenseEntries = array_filter($entries, fn (array $entry): bool => $entry['recurrence']->type === TransactionType::EXPENSE);

        $totalMonthlyIncomeCents = array_sum(array_column($incomeEntries, 'monthly_equivalent_cents'));
        $totalMonthlyExpenseCents = array_sum(array_column($expenseEntries, 'monthly_equivalent_cents'));

        return [
            'total_monthly_income_cents' => $totalMonthlyIncomeCents,
            'total_monthly_expense_cents' => $totalMonthlyExpenseCents,
            'total_annual_income_cents' => array_sum(array_column($incomeEntries, 'annual_cost_cents')),
            'total_annual_expense_cents' => array_sum(array_column($expenseEntries, 'annual_cost_cents')),
            'net_monthly_cents' => $totalMonthlyIncomeCents - $totalMonthlyExpenseCents,
            'count' => count($entries),
        ];
    }
}
