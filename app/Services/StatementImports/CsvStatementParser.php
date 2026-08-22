<?php

namespace App\Services\StatementImports;

use App\Data\StatementImports\CsvImportMapping;
use App\Data\StatementImports\ParsedStatementTransaction;
use App\Enum\TransactionType;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Faz o parse de um CSV de extrato bancário. Cada banco exporta colunas em
 * posições diferentes, então o mapeamento (qual coluna é data/descrição/
 * valor, formato de data, delimitador) vem de `CsvImportMapping`, informado
 * pelo cliente a cada importação. Assume uma única coluna de valor com sinal
 * (negativo = despesa, positivo = receita) — o formato usado pela maioria
 * dos bancos brasileiros.
 */
final class CsvStatementParser implements StatementParser
{
    /** @return array<int, ParsedStatementTransaction> */
    public function parse(string $contents, ?CsvImportMapping $mapping): array
    {
        if ($mapping === null) {
            throw new InvalidArgumentException('CsvStatementParser requer um CsvImportMapping.');
        }

        $rows = $this->splitRows($contents, $mapping->delimiter);

        if ($mapping->hasHeader && $rows !== []) {
            array_shift($rows);
        }

        $transactions = [];
        $seen = [];

        foreach ($rows as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $description = trim($row[$mapping->descriptionColumn] ?? '') ?: 'Transação importada';
            $date = $this->parseDate(trim($row[$mapping->dateColumn] ?? ''), $mapping->dateFormat);
            $amount = $this->parseAmount(trim($row[$mapping->amountColumn] ?? ''));

            if ($date === null || $amount === null) {
                continue;
            }

            $key = sha1($date->toDateString().'|'.$description.'|'.$amount);
            $externalId = $this->dedupExternalId($key, $seen);

            $transactions[] = new ParsedStatementTransaction(
                externalId: "csv:{$externalId}",
                description: $description,
                amountCents: (int) round(abs($amount) * 100),
                date: $date,
                type: $amount < 0 ? TransactionType::EXPENSE : TransactionType::INCOME,
            );
        }

        return $transactions;
    }

    /** @return array<int, array<int, string>> */
    private function splitRows(string $contents, string $delimiter): array
    {
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        $lines = array_filter($lines, static fn (string $line): bool => trim($line) !== '');

        return array_map(static fn (string $line): array => str_getcsv($line, $delimiter, '"', '\\'), $lines);
    }

    /** @param  array<int, string>  $row */
    private function isBlankRow(array $row): bool
    {
        return $row === [] || (count($row) === 1 && trim((string) $row[0]) === '');
    }

    private function parseDate(string $raw, string $format): ?Carbon
    {
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat($format, $raw)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function parseAmount(string $raw): ?float
    {
        if ($raw === '') {
            return null;
        }

        // Aceita tanto "1234.56" quanto o formato BR "1.234,56".
        $normalized = str_contains($raw, ',') ? str_replace(['.', ','], ['', '.'], $raw) : $raw;

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /** @param  array<string, int>  $seen */
    private function dedupExternalId(string $key, array &$seen): string
    {
        $occurrence = $seen[$key] ??= 0;
        $seen[$key]++;

        return $occurrence === 0 ? $key : "{$key}-{$occurrence}";
    }
}
