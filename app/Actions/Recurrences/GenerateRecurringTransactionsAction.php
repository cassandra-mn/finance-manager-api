<?php

namespace App\Actions\Recurrences;

use App\Data\Recurrences\GenerateRecurringTransactionsSummary;
use App\Enum\TransactionStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\Transaction;
use App\Support\RecurrenceDateResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gera as transações pendentes de recorrências ativas dentro de uma janela
 * configurável (`config('finance.recurrences.generation_days')`), evitando
 * que uma ocorrência só apareça no dia exato do vencimento.
 *
 * Idempotência: cada ocorrência é identificada por (recurrence_id, due_date).
 * A aplicação verifica existência antes de inserir; o banco garante o mesmo
 * via índice único parcial (ver migration transactions_recurrence_due_date_unique),
 * usado como rede de segurança contra execuções concorrentes.
 */
final class GenerateRecurringTransactionsAction
{
    public function execute(?Carbon $referenceDate = null): GenerateRecurringTransactionsSummary
    {
        $referenceDate = ($referenceDate ?? Carbon::today())->copy()->startOfDay();
        $windowEnd = $referenceDate->copy()->addDays((int) config('finance.recurrences.generation_days'));

        $summary = new GenerateRecurringTransactionsSummary;

        Recurrence::query()
            ->where('is_active', true)
            ->where('next_due_date', '<=', $windowEnd->toDateString())
            ->chunkById(100, function ($recurrences) use ($windowEnd, $summary): void {
                foreach ($recurrences as $recurrence) {
                    $summary->recurrencesProcessed++;

                    try {
                        $this->processRecurrence($recurrence, $windowEnd, $summary);
                    } catch (Throwable $e) {
                        $summary->errors++;
                        Log::error('finance.recurrences.generation_failed', [
                            'recurrence_id' => $recurrence->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $summary;
    }

    private function processRecurrence(Recurrence $recurrence, Carbon $windowEnd, GenerateRecurringTransactionsSummary $summary): void
    {
        DB::transaction(function () use ($recurrence, $windowEnd, $summary): void {
            $occurrence = $recurrence->next_due_date->copy();

            while ($occurrence->lte($windowEnd) && ($recurrence->end_date === null || $occurrence->lte($recurrence->end_date))) {
                if (! $this->isAccountUsable($recurrence)) {
                    Log::warning('finance.recurrences.invalid_account', [
                        'recurrence_id' => $recurrence->id,
                        'account_id' => $recurrence->account_id,
                    ]);
                    $summary->occurrencesSkipped++;

                    return;
                }

                if (! $this->isCategoryUsable($recurrence)) {
                    Log::warning('finance.recurrences.invalid_category', [
                        'recurrence_id' => $recurrence->id,
                        'category_id' => $recurrence->category_id,
                    ]);
                    $summary->occurrencesSkipped++;

                    return;
                }

                if ($this->occurrenceExists($recurrence, $occurrence)) {
                    $summary->occurrencesSkipped++;
                } else {
                    $this->createOccurrence($recurrence, $occurrence, $summary);
                }

                $occurrence = RecurrenceDateResolver::next($recurrence->frequency, $recurrence->start_date, $occurrence, $recurrence->interval);
                $recurrence->next_due_date = $occurrence;
            }

            $recurrence->save();
        });
    }

    private function isAccountUsable(Recurrence $recurrence): bool
    {
        return Account::query()
            ->whereKey($recurrence->account_id)
            ->where('is_active', true)
            ->exists();
    }

    private function isCategoryUsable(Recurrence $recurrence): bool
    {
        if ($recurrence->category_id === null) {
            return true;
        }

        return Category::query()->whereKey($recurrence->category_id)->exists();
    }

    private function occurrenceExists(Recurrence $recurrence, Carbon $dueDate): bool
    {
        return Transaction::query()
            ->where('recurrence_id', $recurrence->id)
            ->whereDate('due_date', $dueDate->toDateString())
            ->exists();
    }

    private function createOccurrence(Recurrence $recurrence, Carbon $dueDate, GenerateRecurringTransactionsSummary $summary): void
    {
        try {
            DB::transaction(function () use ($recurrence, $dueDate): void {
                Transaction::create([
                    'user_id' => $recurrence->user_id,
                    'account_id' => $recurrence->account_id,
                    'category_id' => $recurrence->category_id,
                    'recurrence_id' => $recurrence->id,
                    'type' => $recurrence->type,
                    'entry_type' => $recurrence->entry_type,
                    'status' => TransactionStatus::PENDING,
                    'description' => $recurrence->description,
                    'amount_cents' => $recurrence->amount_cents,
                    'due_date' => $dueDate->toDateString(),
                    'notes' => $recurrence->notes,
                ]);
            });

            $summary->transactionsCreated++;
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'transactions_recurrence_due_date_unique')) {
                throw $e;
            }

            $summary->occurrencesSkipped++;
            Log::warning('finance.recurrences.duplicate_occurrence_prevented', [
                'recurrence_id' => $recurrence->id,
                'due_date' => $dueDate->toDateString(),
            ]);
        }
    }
}
