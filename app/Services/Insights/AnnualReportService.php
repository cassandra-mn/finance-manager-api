<?php

namespace App\Services\Insights;

use App\Enum\TransactionType;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Carbon;

/**
 * "Ano em revisão": totais mensais de receita/despesa de um ano inteiro,
 * maiores categorias de gasto do ano, e os meses de melhor/pior saldo.
 * Cálculo puro — não gera nenhum arquivo; o frontend renderiza uma página
 * imprimível a partir deste JSON e delega a exportação em PDF ao próprio
 * navegador (Imprimir → Salvar como PDF), sem precisar de nenhuma
 * dependência nova de geração de PDF em nenhum dos dois lados.
 */
final class AnnualReportService
{
    private const MONTH_LABELS = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public function __construct(
        private readonly TransactionRepository $repository,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     summary: array{total_income_cents: int, total_expense_cents: int, net_cents: int, savings_rate_percentage: ?float},
     *     monthly: list<array{month: int, month_label: string, income_cents: int, expense_cents: int, net_cents: int}>,
     *     top_categories: list<array{category_id: int, category_name: string, category_color: ?string, amount_cents: int, percentage_of_total: float}>,
     *     best_month: ?array{month: int, month_label: string, net_cents: int},
     *     worst_month: ?array{month: int, month_label: string, net_cents: int},
     * }
     */
    public function build(int $userId, int $year, int $topCategories): array
    {
        $monthlyTotals = $this->repository->sumTotalsByMonthForUser($userId, $year);

        $monthly = [];
        $totalIncomeCents = 0;
        $totalExpenseCents = 0;
        $bestMonth = null;
        $worstMonth = null;

        for ($month = 1; $month <= 12; $month++) {
            $key = sprintf('%04d-%02d', $year, $month);

            // $row->type vem de um selectRaw, mas Transaction::query()->get()
            // ainda hidrata modelos reais com o cast de enum aplicado — por
            // isso a comparação é enum-a-enum, não enum-a-string (mesmo
            // detalhe já corrigido em CashFlowForecastService).
            $incomeCents = (int) $monthlyTotals
                ->first(fn (object $row): bool => $row->month === $key && $row->type === TransactionType::INCOME)
                ?->total_cents ?? 0;
            $expenseCents = (int) $monthlyTotals
                ->first(fn (object $row): bool => $row->month === $key && $row->type === TransactionType::EXPENSE)
                ?->total_cents ?? 0;
            $netCents = $incomeCents - $expenseCents;

            $totalIncomeCents += $incomeCents;
            $totalExpenseCents += $expenseCents;

            $entry = [
                'month' => $month,
                'month_label' => self::MONTH_LABELS[$month],
                'income_cents' => $incomeCents,
                'expense_cents' => $expenseCents,
                'net_cents' => $netCents,
            ];
            $monthly[] = $entry;

            if ($incomeCents > 0 || $expenseCents > 0) {
                if ($bestMonth === null || $netCents > $bestMonth['net_cents']) {
                    $bestMonth = ['month' => $month, 'month_label' => self::MONTH_LABELS[$month], 'net_cents' => $netCents];
                }
                if ($worstMonth === null || $netCents < $worstMonth['net_cents']) {
                    $worstMonth = ['month' => $month, 'month_label' => self::MONTH_LABELS[$month], 'net_cents' => $netCents];
                }
            }
        }

        $categories = $this->repository->sumExpensesByCategory($userId, Carbon::create($year, 1, 1), Carbon::create($year, 12, 31));

        $totalCategoryExpenseCents = (int) $categories->sum('total_cents');
        $topCategoriesList = $categories->take($topCategories)->map(fn (object $category): array => [
            'category_id' => (int) $category->category_id,
            'category_name' => $category->category_name,
            'category_color' => $category->category_color,
            'amount_cents' => (int) $category->total_cents,
            'percentage_of_total' => $totalCategoryExpenseCents > 0
                ? round(((int) $category->total_cents) / $totalCategoryExpenseCents * 100, 2)
                : 0.0,
        ])->values()->all();

        return [
            'year' => $year,
            'summary' => [
                'total_income_cents' => $totalIncomeCents,
                'total_expense_cents' => $totalExpenseCents,
                'net_cents' => $totalIncomeCents - $totalExpenseCents,
                'savings_rate_percentage' => $totalIncomeCents > 0
                    ? round(($totalIncomeCents - $totalExpenseCents) / $totalIncomeCents * 100, 2)
                    : null,
            ],
            'monthly' => $monthly,
            'top_categories' => $topCategoriesList,
            'best_month' => $bestMonth,
            'worst_month' => $worstMonth,
        ];
    }
}
