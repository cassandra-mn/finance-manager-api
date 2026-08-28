<?php

namespace App\Services\Insights;

use App\Repositories\AccountRepository;
use App\Services\AccountBalanceService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Versão simples do planejador de quitação de dívida: dado um valor fixo
 * pago por mês (e uma taxa de juros opcional), projeta em quantos meses o
 * saldo devedor atual de uma conta chega a zero, mês a mês, sem depender de
 * um conceito novo de "dívida" no modelo — qualquer conta com saldo negativo
 * (cartão de crédito com fatura em aberto, cheque especial etc.) serve.
 */
final class DebtPayoffPlanService
{
    // 30 anos: teto pra não rodar um loop indefinido quando o pagamento
    // mensal é grande demais em relação aos juros pra quitar num prazo
    // razoável — sinaliza como erro de validação em vez de retornar um
    // cronograma gigante.
    private const MAX_MONTHS = 360;

    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly AccountBalanceService $balanceService,
    ) {}

    public function build(int $userId, int $accountId, int $monthlyPaymentCents, float $annualInterestRatePercentage): array
    {
        if (! $this->accountRepository->existsActiveForUser($userId, $accountId)) {
            throw ValidationException::withMessages([
                'account_id' => ['Conta não encontrada.'],
            ]);
        }

        $account = $this->accountRepository->find($accountId);
        $currentBalance = $this->balanceService->calculateCurrentBalance($account);

        if (! $currentBalance->isNegative()) {
            throw ValidationException::withMessages([
                'account_id' => ['Esta conta não tem saldo devedor no momento.'],
            ]);
        }

        $debtCents = abs($currentBalance->cents);
        $monthlyRate = $annualInterestRatePercentage / 100 / 12;
        $minimumPaymentCents = (int) ceil($debtCents * $monthlyRate);

        if ($monthlyRate > 0 && $monthlyPaymentCents <= $minimumPaymentCents) {
            $minimumFormatted = number_format($minimumPaymentCents / 100, 2, ',', '.');

            throw ValidationException::withMessages([
                'monthly_payment_cents' => ["O valor mensal precisa ser maior que os juros do período (R$ {$minimumFormatted}) para que a dívida seja quitada."],
            ]);
        }

        $schedule = [];
        $balanceCents = $debtCents;
        $totalInterestCents = 0;
        $month = 0;

        while ($balanceCents > 0 && $month < self::MAX_MONTHS) {
            $month++;
            $interestCents = (int) round($balanceCents * $monthlyRate);
            $principalCents = min($monthlyPaymentCents - $interestCents, $balanceCents);
            $endingBalanceCents = $balanceCents - $principalCents;

            $schedule[] = [
                'month' => $month,
                'starting_balance_cents' => $balanceCents,
                'interest_cents' => $interestCents,
                'principal_cents' => $principalCents,
                'ending_balance_cents' => $endingBalanceCents,
            ];

            $totalInterestCents += $interestCents;
            $balanceCents = $endingBalanceCents;
        }

        if ($balanceCents > 0) {
            throw ValidationException::withMessages([
                'monthly_payment_cents' => ['Nesse ritmo a dívida não seria quitada em 30 anos. Aumente o valor do pagamento mensal.'],
            ]);
        }

        return [
            'account_id' => $account->id,
            'current_debt_cents' => $debtCents,
            'monthly_payment_cents' => $monthlyPaymentCents,
            'annual_interest_rate_percentage' => $annualInterestRatePercentage,
            'months_to_payoff' => $month,
            'payoff_date' => Carbon::today()->addMonthsNoOverflow($month)->toDateString(),
            'total_interest_cents' => $totalInterestCents,
            'total_paid_cents' => $debtCents + $totalInterestCents,
            'schedule' => $schedule,
        ];
    }
}
