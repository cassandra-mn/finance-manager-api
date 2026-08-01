<?php

namespace Tests\Unit\Services\StatementImports;

use App\Enum\TransactionType;
use App\Services\StatementImports\OfxStatementParser;
use PHPUnit\Framework\TestCase;

class OfxStatementParserTest extends TestCase
{
    public function test_parses_ofx1_sgml_transactions(): void
    {
        $transactions = (new OfxStatementParser)->parse($this->sgmlFixture());

        $this->assertCount(2, $transactions);

        $this->assertSame('ofx:202607200001', $transactions[0]->externalId);
        $this->assertSame('Supermercado', $transactions[0]->description);
        $this->assertSame(15050, $transactions[0]->amountCents);
        $this->assertSame('2026-07-20', $transactions[0]->date->toDateString());
        $this->assertSame(TransactionType::EXPENSE, $transactions[0]->type);

        $this->assertSame('ofx:202607250001', $transactions[1]->externalId);
        $this->assertSame(500000, $transactions[1]->amountCents);
        $this->assertSame(TransactionType::INCOME, $transactions[1]->type);
    }

    public function test_parses_ofx2_xml_transactions(): void
    {
        $transactions = (new OfxStatementParser)->parse($this->xmlFixture());

        $this->assertCount(1, $transactions);
        $this->assertSame('ofx:XML0001', $transactions[0]->externalId);
        $this->assertSame(9990, $transactions[0]->amountCents);
        $this->assertSame(TransactionType::EXPENSE, $transactions[0]->type);
    }

    public function test_falls_back_to_a_deterministic_hash_when_fitid_is_missing(): void
    {
        $ofx = <<<'OFX'
            <OFX>
            <BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>
            <STMTTRN>
            <TRNTYPE>DEBIT
            <DTPOSTED>20260720
            <TRNAMT>-10.00
            <MEMO>Sem FITID
            </STMTTRN>
            </BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1>
            </OFX>
            OFX;

        $first = (new OfxStatementParser)->parse($ofx);
        $second = (new OfxStatementParser)->parse($ofx);

        $this->assertCount(1, $first);
        $this->assertStringStartsWith('ofx:', $first[0]->externalId);
        $this->assertSame($first[0]->externalId, $second[0]->externalId);
    }

    public function test_duplicate_transactions_within_the_same_file_get_distinct_ids(): void
    {
        $ofx = <<<'OFX'
            <OFX>
            <BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>
            <STMTTRN>
            <TRNTYPE>DEBIT
            <DTPOSTED>20260720
            <TRNAMT>-10.00
            <MEMO>Repetida
            </STMTTRN>
            <STMTTRN>
            <TRNTYPE>DEBIT
            <DTPOSTED>20260720
            <TRNAMT>-10.00
            <MEMO>Repetida
            </STMTTRN>
            </BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1>
            </OFX>
            OFX;

        $transactions = (new OfxStatementParser)->parse($ofx);

        $this->assertCount(2, $transactions);
        $this->assertNotSame($transactions[0]->externalId, $transactions[1]->externalId);
    }

    private function sgmlFixture(): string
    {
        return <<<'OFX'
            OFXHEADER:100
            DATA:OFXSGML
            VERSION:102
            SECURITY:NONE
            ENCODING:USASCII
            CHARSET:1252

            <OFX>
            <BANKMSGSRSV1>
            <STMTTRNRS>
            <STMTRS>
            <BANKACCTFROM>
            <BANKID>001
            <ACCTID>12345-6
            </BANKACCTFROM>
            <BANKTRANLIST>
            <STMTTRN>
            <TRNTYPE>DEBIT
            <DTPOSTED>20260720120000
            <TRNAMT>-150.50
            <FITID>202607200001
            <MEMO>Supermercado
            </STMTTRN>
            <STMTTRN>
            <TRNTYPE>CREDIT
            <DTPOSTED>20260725120000
            <TRNAMT>5000.00
            <FITID>202607250001
            <MEMO>Salário
            </STMTTRN>
            </BANKTRANLIST>
            </STMTRS>
            </STMTTRNRS>
            </BANKMSGSRSV1>
            </OFX>
            OFX;
    }

    private function xmlFixture(): string
    {
        return <<<'OFX'
            <?xml version="1.0" encoding="UTF-8"?>
            <?OFX OFXHEADER="200" VERSION="211" SECURITY="NONE" OLDFILEUID="NONE" NEWFILEUID="NONE"?>
            <OFX>
              <BANKMSGSRSV1>
                <STMTTRNRS>
                  <STMTRS>
                    <BANKTRANLIST>
                      <STMTTRN>
                        <TRNTYPE>DEBIT</TRNTYPE>
                        <DTPOSTED>20260722</DTPOSTED>
                        <TRNAMT>-99.90</TRNAMT>
                        <FITID>XML0001</FITID>
                        <NAME>Farmácia</NAME>
                      </STMTTRN>
                    </BANKTRANLIST>
                  </STMTRS>
                </STMTTRNRS>
              </BANKMSGSRSV1>
            </OFX>
            OFX;
    }
}
