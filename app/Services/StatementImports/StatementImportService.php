<?php

namespace App\Services\StatementImports;

use App\Data\StatementImports\CreateStatementImportData;
use App\Data\StatementImports\ParsedStatementTransaction;
use App\Enum\StatementImportFormat;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionOrigin;
use App\Enum\TransactionStatus;
use App\Exceptions\ServiceException;
use App\Http\Requests\StatementImports\StoreStatementImportRequest;
use App\Models\Account;
use App\Models\StatementImport;
use App\Repositories\StatementImportRepository;
use App\Repositories\TransactionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importa transações de um arquivo de extrato (OFX ou CSV) para uma conta
 * específica, escolhida pelo usuário antes do upload (sem tentativa de
 * detectar/criar conta automaticamente a partir do arquivo). Transações são
 * identificadas por (account_id, external_id) — reimportar o mesmo arquivo
 * nunca duplica lançamentos (ver migration
 * transactions_account_external_id_unique), a mesma técnica de dedup usada
 * pelas recorrências.
 *
 * Toda transação importada entra sempre como `status = paid`: representa um
 * lançamento bancário já efetivado, diferente do fluxo pending/paid usado
 * para lançamentos manuais/recorrências.
 */
final class StatementImportService
{
    public function __construct(
        private readonly OfxStatementParser $ofxParser,
        private readonly CsvStatementParser $csvParser,
        private readonly StatementImportRepository $repository,
        private readonly TransactionRepository $transactionRepository,
    ) {}

    /** @return Collection<int, StatementImport> */
    public function listForAccount(Account $account): Collection
    {
        return $this->repository->listForAccount($account->user_id, $account->id);
    }

    public function import(Account $account, StoreStatementImportRequest $request): StatementImport
    {
        $data = CreateStatementImportData::fromRequest($request);
        $contents = (string) $request->file('file')?->get();

        try {
            $parsedTransactions = match ($data->format) {
                StatementImportFormat::OFX => $this->ofxParser->parse($contents),
                StatementImportFormat::CSV => $this->csvParser->parse($contents, $data->csvMapping),
            };
        } catch (Throwable $e) {
            Log::error('finance.statement_imports.parse_failed', [
                'account_id' => $account->id,
                'format' => $data->format->value,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível interpretar o arquivo enviado.', previous: $e);
        }

        $created = 0;
        $skipped = 0;

        foreach ($parsedTransactions as $parsedTransaction) {
            if ($this->importTransaction($account, $parsedTransaction)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        try {
            return $this->repository->create([
                'user_id' => $account->user_id,
                'account_id' => $account->id,
                'format' => $data->format,
                'original_filename' => $data->originalFilename,
                'transactions_created' => $created,
                'transactions_skipped' => $skipped,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.statement_imports.record_failed', [
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('A importação foi processada, mas não foi possível registrar o histórico.', previous: $e);
        }
    }

    private function importTransaction(Account $account, ParsedStatementTransaction $parsed): bool
    {
        try {
            DB::transaction(function () use ($account, $parsed): void {
                $this->transactionRepository->createFromExternal([
                    'user_id' => $account->user_id,
                    'account_id' => $account->id,
                    'category_id' => null,
                    'recurrence_id' => null,
                    'external_id' => $parsed->externalId,
                    'origin' => TransactionOrigin::STATEMENT_IMPORT,
                    'type' => $parsed->type,
                    'entry_type' => TransactionEntryType::SINGLE,
                    'status' => TransactionStatus::PAID,
                    'description' => $parsed->description,
                    'amount_cents' => $parsed->amountCents,
                    'due_date' => $parsed->date->toDateString(),
                    'paid_at' => $parsed->date,
                    'notes' => null,
                ]);
            });

            return true;
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'transactions_account_external_id_unique')) {
                throw $e;
            }

            Log::warning('finance.statement_imports.duplicate_transaction_prevented', [
                'account_id' => $account->id,
                'external_id' => $parsed->externalId,
            ]);

            return false;
        }
    }
}
