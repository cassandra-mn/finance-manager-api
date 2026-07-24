<?php

namespace App\Services;

use App\Data\Recurrences\CreateRecurrenceData;
use App\Data\Recurrences\GenerateRecurringTransactionsSummary;
use App\Data\Recurrences\RecurrenceFiltersData;
use App\Data\Recurrences\UpdateRecurrenceData;
use App\Enum\TransactionStatus;
use App\Exceptions\ServiceException;
use App\Http\Requests\Recurrences\ListRecurrencesRequest;
use App\Http\Requests\Recurrences\StoreRecurrenceRequest;
use App\Http\Requests\Recurrences\UpdateRecurrenceRequest;
use App\Models\Recurrence;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\RecurrenceRepository;
use App\Repositories\TransactionRepository;
use App\Support\RecurrenceDateResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A geração de ocorrências roda dentro de uma janela configurável
 * (`config('finance.recurrences.generation_days')`), evitando que uma
 * ocorrência só apareça no dia exato do vencimento.
 *
 * Idempotência: cada ocorrência é identificada por (recurrence_id, due_date).
 * A aplicação verifica existência antes de inserir; o banco garante o mesmo
 * via índice único parcial (ver migration transactions_recurrence_due_date_unique),
 * usado como rede de segurança contra execuções concorrentes.
 */
final class RecurrenceService
{
    public function __construct(
        private readonly RecurrenceRepository $recurrenceRepository,
        private readonly AccountRepository $accountRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TransactionRepository $transactionRepository,
    ) {}

    /** @return Collection<int, Recurrence> */
    public function listForUser(ListRecurrencesRequest $request): Collection
    {
        return $this->recurrenceRepository->listForUser(
            $request->user()->id,
            RecurrenceFiltersData::fromRequest($request),
        );
    }

    public function create(StoreRecurrenceRequest $request): Recurrence
    {
        $data = CreateRecurrenceData::fromRequest($request, $request->user()->id);

        try {
            return $this->recurrenceRepository->create([
                'user_id' => $data->userId,
                'account_id' => $data->accountId,
                'category_id' => $data->categoryId,
                'type' => $data->type,
                'entry_type' => $data->entryType,
                'description' => $data->description,
                'amount_cents' => $data->amountCents,
                'frequency' => $data->frequency,
                'start_date' => $data->startDate,
                'next_due_date' => $data->nextDueDate,
                'end_date' => $data->endDate,
                'notes' => $data->notes,
                'is_active' => true,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.recurrences.create_failed', [
                'user_id' => $data->userId,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível criar a recorrência.', previous: $e);
        }
    }

    public function update(Recurrence $recurrence, UpdateRecurrenceRequest $request): Recurrence
    {
        $data = UpdateRecurrenceData::fromRequest($request);

        try {
            return $this->recurrenceRepository->update($recurrence, array_filter([
                'account_id' => $data->accountId,
                'category_id' => $data->categoryId,
                'type' => $data->type,
                'entry_type' => $data->entryType,
                'description' => $data->description,
                'amount_cents' => $data->amountCents,
                'frequency' => $data->frequency,
                'start_date' => $data->startDate,
                'next_due_date' => $data->nextDueDate,
                'end_date' => $data->endDate,
                'notes' => $data->notes,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (Throwable $e) {
            Log::error('finance.recurrences.update_failed', [
                'recurrence_id' => $recurrence->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível atualizar a recorrência.', previous: $e);
        }
    }

    public function delete(Recurrence $recurrence): void
    {
        try {
            $this->recurrenceRepository->delete($recurrence);
        } catch (Throwable $e) {
            Log::error('finance.recurrences.delete_failed', [
                'recurrence_id' => $recurrence->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível excluir a recorrência.', previous: $e);
        }
    }

    public function pause(Recurrence $recurrence): Recurrence
    {
        if (! $recurrence->is_active) {
            throw ValidationException::withMessages([
                'is_active' => ['Esta recorrência já está pausada.'],
            ]);
        }

        try {
            return $this->recurrenceRepository->update($recurrence, ['is_active' => false]);
        } catch (Throwable $e) {
            Log::error('finance.recurrences.pause_failed', [
                'recurrence_id' => $recurrence->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível pausar a recorrência.', previous: $e);
        }
    }

    public function resume(Recurrence $recurrence): Recurrence
    {
        if ($recurrence->is_active) {
            throw ValidationException::withMessages([
                'is_active' => ['Esta recorrência já está ativa.'],
            ]);
        }

        try {
            return $this->recurrenceRepository->update($recurrence, ['is_active' => true]);
        } catch (Throwable $e) {
            Log::error('finance.recurrences.resume_failed', [
                'recurrence_id' => $recurrence->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível retomar a recorrência.', previous: $e);
        }
    }

    public function generateOccurrences(?Carbon $referenceDate = null): GenerateRecurringTransactionsSummary
    {
        $referenceDate = ($referenceDate ?? Carbon::today())->copy()->startOfDay();
        $windowEnd = $referenceDate->copy()->addDays((int) config('finance.recurrences.generation_days'));

        $summary = new GenerateRecurringTransactionsSummary;

        $this->recurrenceRepository->chunkActiveDueBy(
            $windowEnd,
            100,
            function (Collection $recurrences) use ($windowEnd, $summary): void {
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
            },
        );

        return $summary;
    }

    private function processRecurrence(Recurrence $recurrence, Carbon $windowEnd, GenerateRecurringTransactionsSummary $summary): void
    {
        DB::transaction(function () use ($recurrence, $windowEnd, $summary): void {
            $occurrence = $recurrence->next_due_date->copy();

            while ($occurrence->lte($windowEnd) && ($recurrence->end_date === null || $occurrence->lte($recurrence->end_date))) {
                if (! $this->accountRepository->existsActiveById($recurrence->account_id)) {
                    Log::warning('finance.recurrences.invalid_account', [
                        'recurrence_id' => $recurrence->id,
                        'account_id' => $recurrence->account_id,
                    ]);
                    $summary->occurrencesSkipped++;

                    return;
                }

                if ($recurrence->category_id !== null && ! $this->categoryRepository->existsById($recurrence->category_id)) {
                    Log::warning('finance.recurrences.invalid_category', [
                        'recurrence_id' => $recurrence->id,
                        'category_id' => $recurrence->category_id,
                    ]);
                    $summary->occurrencesSkipped++;

                    return;
                }

                if ($this->transactionRepository->existsForRecurrenceOnDate($recurrence->id, $occurrence)) {
                    $summary->occurrencesSkipped++;
                } else {
                    $this->createOccurrence($recurrence, $occurrence, $summary);
                }

                $occurrence = RecurrenceDateResolver::next($recurrence->frequency, $recurrence->start_date, $occurrence, $recurrence->interval);
                $recurrence->next_due_date = $occurrence;
            }

            $this->recurrenceRepository->save($recurrence);
        });
    }

    private function createOccurrence(Recurrence $recurrence, Carbon $dueDate, GenerateRecurringTransactionsSummary $summary): void
    {
        try {
            DB::transaction(function () use ($recurrence, $dueDate): void {
                $this->transactionRepository->create([
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
