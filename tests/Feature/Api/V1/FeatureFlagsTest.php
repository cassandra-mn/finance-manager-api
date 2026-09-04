<?php

namespace Tests\Feature\Api\V1;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Confirma que o middleware `feature` bloqueia de verdade no backend (404),
 * não só esconde a tela no frontend — e que o núcleo mínimo do app
 * (auth, accounts, categories, transactions) continua funcionando mesmo com
 * todas as flags desligadas ao mesmo tempo.
 */
class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function gatedRoutesProvider(): array
    {
        return [
            'budgets status' => ['budgets', 'GET', '/api/v1/budgets/status'],
            'goals index' => ['goals', 'GET', '/api/v1/goals'],
            'transaction-groups index' => ['invoices', 'GET', '/api/v1/transaction-groups'],
            'recurrences index' => ['recurrences', 'GET', '/api/v1/recurrences'],
            'insights spending-summary' => ['insights_panel', 'GET', '/api/v1/insights/spending-summary'],
            'insights annual-report' => ['annual_report', 'GET', '/api/v1/insights/annual-report'],
            'insights debt-payoff-plan' => ['debt_payoff_plan', 'GET', '/api/v1/insights/debt-payoff-plan'],
            'assistant ask' => ['ai_assistant', 'POST', '/api/v1/assistant/ask'],
            'statement imports index' => ['statement_import', 'GET', '/api/v1/accounts/{account}/statement-imports'],
            'transactions export' => ['transaction_export', 'GET', '/api/v1/transactions/export'],
        ];
    }

    #[DataProvider('gatedRoutesProvider')]
    public function test_route_returns_404_when_its_flag_is_disabled(string $flag, string $method, string $uri): void
    {
        config(["features.{$flag}" => false]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Cria a conta de verdade mesmo no caso desabilitado: o Laravel
        // resolve o route-model-binding antes de rodar o middleware da
        // rota, então um {account} inexistente já daria 404 por conta
        // própria — o teste ficaria "verde" mesmo sem o middleware bloquear
        // nada. Com uma conta real, o único jeito de dar 404 aqui é o
        // middleware `feature` mesmo.
        if (str_contains($uri, '{account}')) {
            $account = Account::factory()->for($user)->create();
            $uri = str_replace('{account}', (string) $account->id, $uri);
        }

        $this->json($method, $uri)->assertNotFound();
    }

    #[DataProvider('gatedRoutesProvider')]
    public function test_route_responds_normally_when_its_flag_is_enabled(string $flag, string $method, string $uri): void
    {
        config(["features.{$flag}" => true]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        if (str_contains($uri, '{account}')) {
            $account = Account::factory()->for($user)->create();
            $uri = str_replace('{account}', (string) $account->id, $uri);
        }

        $status = $this->json($method, $uri)->getStatusCode();

        $this->assertNotSame(404, $status, "A rota {$uri} não deveria retornar 404 com a flag '{$flag}' ligada.");
    }

    public function test_whatsapp_webhook_is_blocked_when_its_flag_is_disabled(): void
    {
        config(['features.whatsapp' => false]);

        $this->getJson('/api/v1/whatsapp/webhook')->assertNotFound();
    }

    public function test_core_routes_stay_available_even_with_every_flag_disabled(): void
    {
        config([
            'features.budgets' => false,
            'features.goals' => false,
            'features.invoices' => false,
            'features.recurrences' => false,
            'features.insights_panel' => false,
            'features.annual_report' => false,
            'features.debt_payoff_plan' => false,
            'features.ai_assistant' => false,
            'features.whatsapp' => false,
            'features.statement_import' => false,
            'features.transaction_export' => false,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/accounts')->assertOk();
        $this->getJson('/api/v1/categories')->assertOk();
        $this->getJson('/api/v1/transactions')->assertOk();
    }
}
