<?php

namespace Tests\Feature\Api\V1;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_export_transactions(): void
    {
        $this->getJson('/api/v1/transactions/export')->assertUnauthorized();
    }

    public function test_exports_a_csv_with_a_header_row_and_one_row_per_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['name' => 'Itaú Uniclass']);
        $category = Category::factory()->for($user)->expense()->create(['name' => 'Alimentação']);

        Transaction::factory()->for($user)->for($account)->for($category)->expense()
            ->create(['description' => 'Mercado', 'amount_cents' => 15050, 'due_date' => '2026-08-05']);

        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/transactions/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));

        // Linha 0 é o cabeçalho.
        $this->assertStringContainsString('Descrição', $lines[0]);
        $this->assertStringContainsString('Mercado', $lines[1]);
        $this->assertStringContainsString('Alimentação', $lines[1]);
        $this->assertStringContainsString('Itaú Uniclass', $lines[1]);
        $this->assertStringContainsString('150,50', $lines[1]);
    }

    public function test_respects_the_same_filters_as_the_transaction_list(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $categoryA = Category::factory()->for($user)->expense()->create(['name' => 'Categoria A']);
        $categoryB = Category::factory()->for($user)->expense()->create(['name' => 'Categoria B']);

        Transaction::factory()->for($user)->for($account)->for($categoryA)->expense()
            ->create(['description' => 'Da categoria A', 'due_date' => '2026-08-05']);
        Transaction::factory()->for($user)->for($account)->for($categoryB)->expense()
            ->create(['description' => 'Da categoria B', 'due_date' => '2026-08-05']);

        Sanctum::actingAs($user);

        $response = $this->get("/api/v1/transactions/export?category_id={$categoryA->id}");
        $content = $response->streamedContent();

        $this->assertStringContainsString('Da categoria A', $content);
        $this->assertStringNotContainsString('Da categoria B', $content);
    }

    public function test_export_is_not_capped_by_the_pagination_limit(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($account)->count(150)
            ->create(['due_date' => '2026-08-05']);

        Sanctum::actingAs($user);

        // Confirma que a listagem paginada normal é limitada a 100 (o teto
        // configurado, o máximo aceito pela validação), enquanto o export
        // traz as 150 linhas.
        $listResponse = $this->getJson('/api/v1/transactions?per_page=100');
        $listResponse->assertOk();
        $this->assertCount(100, $listResponse->json('data'));

        $exportResponse = $this->get('/api/v1/transactions/export');
        $lines = array_filter(explode("\n", trim($exportResponse->streamedContent())));

        // -1 pela linha de cabeçalho.
        $this->assertSame(150, count($lines) - 1);
    }

    public function test_another_users_transactions_never_appear_in_the_export(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();

        Transaction::factory()->for($userB)->for($accountB)
            ->create(['description' => 'Transação do outro usuário']);

        Sanctum::actingAs($userA);

        $response = $this->get('/api/v1/transactions/export');

        $this->assertStringNotContainsString('Transação do outro usuário', $response->streamedContent());
    }
}
