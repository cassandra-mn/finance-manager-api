<?php

namespace Tests\Unit\Services\StatementImports;

use App\Data\StatementImports\CsvImportMapping;
use App\Enum\TransactionType;
use App\Services\StatementImports\CsvStatementParser;
use PHPUnit\Framework\TestCase;

class CsvStatementParserTest extends TestCase
{
    public function test_parses_a_semicolon_separated_csv_with_a_header_and_brazilian_decimal_format(): void
    {
        $csv = "Data;Descrição;Valor\n20/07/2026;Supermercado;-150,50\n25/07/2026;Salário;5000,00\n";

        $transactions = (new CsvStatementParser)->parse($csv, $this->mapping());

        $this->assertCount(2, $transactions);

        $this->assertSame('Supermercado', $transactions[0]->description);
        $this->assertSame(15050, $transactions[0]->amountCents);
        $this->assertSame('2026-07-20', $transactions[0]->date->toDateString());
        $this->assertSame(TransactionType::EXPENSE, $transactions[0]->type);

        $this->assertSame(500000, $transactions[1]->amountCents);
        $this->assertSame(TransactionType::INCOME, $transactions[1]->type);
    }

    public function test_skips_rows_with_an_unparseable_date_or_amount(): void
    {
        $csv = "Data;Descrição;Valor\n20/07/2026;Válida;-10,00\ndata-invalida;Inválida;-10,00\n25/07/2026;Sem valor;\n";

        $transactions = (new CsvStatementParser)->parse($csv, $this->mapping());

        $this->assertCount(1, $transactions);
        $this->assertSame('Válida', $transactions[0]->description);
    }

    public function test_ignores_blank_lines(): void
    {
        $csv = "Data;Descrição;Valor\n20/07/2026;Supermercado;-10,00\n\n\n25/07/2026;Salário;100,00\n";

        $transactions = (new CsvStatementParser)->parse($csv, $this->mapping());

        $this->assertCount(2, $transactions);
    }

    public function test_reparsing_the_same_file_produces_the_same_external_ids(): void
    {
        $csv = "Data;Descrição;Valor\n20/07/2026;Repetida;-10,00\n20/07/2026;Repetida;-10,00\n";

        $first = (new CsvStatementParser)->parse($csv, $this->mapping());
        $second = (new CsvStatementParser)->parse($csv, $this->mapping());

        $this->assertNotSame($first[0]->externalId, $first[1]->externalId);
        $this->assertSame($first[0]->externalId, $second[0]->externalId);
        $this->assertSame($first[1]->externalId, $second[1]->externalId);
    }

    public function test_accepts_a_plain_dot_decimal_amount_without_a_header(): void
    {
        $csv = "20/07/2026,Compra,-10.50\n";

        $mapping = new CsvImportMapping(
            dateColumn: 0,
            descriptionColumn: 1,
            amountColumn: 2,
            dateFormat: 'd/m/Y',
            delimiter: ',',
            hasHeader: false,
        );

        $transactions = (new CsvStatementParser)->parse($csv, $mapping);

        $this->assertCount(1, $transactions);
        $this->assertSame(1050, $transactions[0]->amountCents);
    }

    private function mapping(): CsvImportMapping
    {
        return new CsvImportMapping(
            dateColumn: 0,
            descriptionColumn: 1,
            amountColumn: 2,
            dateFormat: 'd/m/Y',
            delimiter: ';',
            hasHeader: true,
        );
    }
}
