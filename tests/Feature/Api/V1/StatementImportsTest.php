<?php

namespace Tests\Feature\Api\V1;

use App\Enum\TransactionOrigin;
use App\Enum\TransactionStatus;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatementImportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_import_a_statement(): void
    {
        $account = Account::factory()->for(User::factory()->create())->create();

        $response = $this->postMultipart("/api/v1/accounts/{$account->id}/statement-imports", [
            'format' => 'ofx',
            'file' => UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofxFixture()),
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_can_import_an_ofx_statement_into_their_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postMultipart("/api/v1/accounts/{$account->id}/statement-imports", [
            'format' => 'ofx',
            'file' => UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofxFixture()),
        ]);

        $response->assertCreated()
            ->assertJsonPath('account_id', $account->id)
            ->assertJsonPath('format', 'ofx')
            ->assertJsonPath('transactions_created', 2)
            ->assertJsonPath('transactions_skipped', 0);

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'external_id' => 'ofx:202607200001',
            'origin' => TransactionOrigin::STATEMENT_IMPORT->value,
            'status' => TransactionStatus::PAID->value,
            'amount_cents' => 15050,
        ]);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'external_id' => 'ofx:202607250001',
            'amount_cents' => 500000,
        ]);
    }

    public function test_reimporting_the_same_ofx_file_does_not_duplicate_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $payload = [
            'format' => 'ofx',
            'file' => UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofxFixture()),
        ];

        $this->postMultipart("/api/v1/accounts/{$account->id}/statement-imports", $payload)->assertCreated();
        $countAfterFirstImport = Transaction::query()->count();

        $payload['file'] = UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofxFixture());
        $response = $this->postMultipart("/api/v1/accounts/{$account->id}/statement-imports", $payload);

        $response->assertCreated()
            ->assertJsonPath('transactions_created', 0)
            ->assertJsonPath('transactions_skipped', 2);
        $this->assertSame($countAfterFirstImport, Transaction::query()->count());
    }

    public function test_user_can_import_a_csv_statement_with_a_custom_mapping(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $csv = "Data;Descrição;Valor\n20/07/2026;Supermercado;-150,50\n25/07/2026;Salário;5000,00\n";

        $response = $this->postMultipart("/api/v1/accounts/{$account->id}/statement-imports", [
            'format' => 'csv',
            'file' => UploadedFile::fake()->createWithContent('extrato.csv', $csv),
            'delimiter' => ';',
            'has_header' => true,
            'date_column' => 0,
            'description_column' => 1,
            'amount_column' => 2,
            'date_format' => 'd/m/Y',
        ]);

        $response->assertCreated()
            ->assertJsonPath('format', 'csv')
            ->assertJsonPath('transactions_created', 2);

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'description' => 'Supermercado',
            'amount_cents' => 15050,
        ]);
    }

    public function test_csv_import_fails_validation_without_a_column_mapping(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postMultipart("/api/v1/accounts/{$account->id}/statement-imports", [
            'format' => 'csv',
            'file' => UploadedFile::fake()->createWithContent('extrato.csv', "a;b;c\n"),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'date_column', 'description_column', 'amount_column', 'date_format',
        ]);
    }

    public function test_user_cannot_import_into_another_users_account(): void
    {
        $account = Account::factory()->for(User::factory()->create())->create();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postMultipart("/api/v1/accounts/{$account->id}/statement-imports", [
            'format' => 'ofx',
            'file' => UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofxFixture()),
        ]);

        $response->assertNotFound();
    }

    public function test_user_can_list_the_import_history_of_an_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postMultipart("/api/v1/accounts/{$account->id}/statement-imports", [
            'format' => 'ofx',
            'file' => UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofxFixture()),
        ])->assertCreated();

        $response = $this->getJson("/api/v1/accounts/{$account->id}/statement-imports");

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonPath('0.transactions_created', 2);
    }

    private function postMultipart(string $uri, array $data): TestResponse
    {
        return $this->post($uri, $data, ['Accept' => 'application/json']);
    }

    private function ofxFixture(): string
    {
        return <<<'OFX'
            <OFX>
            <BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>
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
            </BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1>
            </OFX>
            OFX;
    }
}
