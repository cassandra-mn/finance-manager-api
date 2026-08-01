<?php

namespace App\Services\StatementImports;

use App\Data\StatementImports\ParsedStatementTransaction;
use App\Enum\TransactionType;
use App\Exceptions\ServiceException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Faz o parse de um arquivo OFX (Open Financial Exchange), nos dois formatos
 * em uso pelos bancos brasileiros: OFX 1.x (SGML, tags-folha sem fechamento)
 * e OFX 2.x (XML bem-formado). Extrai apenas os campos de <STMTTRN> usados
 * pela importação — sem depender de nenhuma biblioteca externa.
 */
final class OfxStatementParser
{
    /** @return array<int, ParsedStatementTransaction> */
    public function parse(string $contents): array
    {
        $document = $this->loadXml($contents);
        $nodes = $document->xpath('//STMTTRN') ?: [];

        $transactions = [];
        $seen = [];

        foreach ($nodes as $node) {
            $date = $this->parseDate((string) $node->DTPOSTED);

            if ($date === null) {
                continue;
            }

            $amount = (float) str_replace(',', '.', (string) $node->TRNAMT);
            $description = trim((string) ($node->MEMO ?: $node->NAME)) ?: 'Transação importada';
            $fitId = trim((string) $node->FITID);

            $key = $fitId !== '' ? $fitId : sha1($date->toDateString().'|'.$description.'|'.$amount);
            $externalId = $this->dedupExternalId($key, $seen);

            $transactions[] = new ParsedStatementTransaction(
                externalId: "ofx:{$externalId}",
                description: $description,
                amountCents: (int) round(abs($amount) * 100),
                date: $date,
                type: $amount < 0 ? TransactionType::EXPENSE : TransactionType::INCOME,
            );
        }

        return $transactions;
    }

    private function loadXml(string $raw): \SimpleXMLElement
    {
        $start = stripos($raw, '<ofx');
        $body = $start !== false ? substr($raw, $start) : $raw;
        $xml = (string) preg_replace('/<([A-Za-z0-9.]+)>([^<\r\n]+)(\r?\n|\r)/', '<$1>$2</$1>$3', $body);

        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();

        if ($document === false) {
            throw new ServiceException('Não foi possível interpretar o arquivo OFX.');
        }

        return $document;
    }

    private function parseDate(string $raw): ?Carbon
    {
        if (strlen($raw) < 8) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Ymd', substr($raw, 0, 8))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /** @param  array<string, int>  $seen */
    private function dedupExternalId(string $key, array &$seen): string
    {
        $occurrence = $seen[$key] ??= 0;
        $seen[$key]++;

        return $occurrence === 0 ? $key : "{$key}-{$occurrence}";
    }
}
