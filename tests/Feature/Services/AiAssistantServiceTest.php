<?php

namespace Tests\Feature\Services;

use App\Enum\AccountType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use App\Services\AiAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AiAssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $structured */
    private function fakeGemini(array $structured, string $finishReason = 'STOP'): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode($structured)],
                            ],
                        ],
                        'finishReason' => $finishReason,
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_happy_path_creates_a_transaction_action_for_an_existing_account_and_category(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $account = Account::factory()->for($user)->create(['type' => AccountType::CREDIT_CARD, 'name' => 'Nubank']);
        $category = Category::factory()->for($user)->expense()->create(['name' => 'Alimentação']);

        $this->fakeGemini([
            'clarification' => null,
            'actions' => [
                [
                    'client_id' => 'a1',
                    'kind' => 'create_transaction',
                    'summary' => 'Despesa de R$ 100,00 em Alimentação, conta Nubank',
                    'transaction' => [
                        'account_id' => $account->id,
                        'account_ref' => null,
                        'category_id' => $category->id,
                        'type' => 'expense',
                        'entry_type' => 'single',
                        'description' => 'Supermercado',
                        'amount' => '100.00',
                        'due_date' => '2026-08-05',
                        'notes' => null,
                    ],
                ],
            ],
        ]);

        $result = app(AiAssistantService::class)->quickAdd('gastei 100 reais no supermercado no Nubank', $user);

        $this->assertNull($result['clarification']);
        $this->assertCount(1, $result['actions']);
        $this->assertSame('transaction', $result['actions'][0]['kind']);
        $this->assertSame($account->id, $result['actions'][0]['payload']['account_id']);
        $this->assertSame($category->id, $result['actions'][0]['payload']['category_id']);
        $this->assertSame(10000, $result['actions'][0]['payload']['amount_cents']);
        $this->assertSame('2026-08-05', $result['actions'][0]['payload']['due_date']);
    }

    public function test_installment_purchase_is_expanded_with_correct_cent_rounding_and_monthly_dates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $account = Account::factory()->for($user)->create(['type' => AccountType::CREDIT_CARD]);

        $this->fakeGemini([
            'clarification' => null,
            'actions' => [
                [
                    'client_id' => 'a1',
                    'kind' => 'create_installment_transactions',
                    'summary' => 'Compra parcelada em 3x',
                    'installment' => [
                        'account_id' => $account->id,
                        'account_ref' => null,
                        'category_id' => null,
                        'type' => 'expense',
                        'description' => 'Notebook',
                        'total_amount' => '100.00',
                        'installments_count' => 3,
                        'first_due_date' => '2026-08-28',
                    ],
                ],
            ],
        ]);

        $result = app(AiAssistantService::class)->quickAdd('comprei um notebook de 100 reais em 3x', $user);

        $this->assertCount(3, $result['actions']);

        $amounts = array_column(array_column($result['actions'], 'payload'), 'amount_cents');
        $this->assertSame([3334, 3333, 3333], $amounts);
        $this->assertSame(10000, array_sum($amounts));

        $dueDates = array_column(array_column($result['actions'], 'payload'), 'due_date');
        $this->assertSame(['2026-08-28', '2026-09-28', '2026-10-28'], $dueDates);

        foreach ($result['actions'] as $action) {
            $this->assertStringContainsString('parcela', $action['summary']);
            $this->assertSame($account->id, $action['payload']['account_id']);
        }
    }

    public function test_action_referencing_an_account_not_owned_by_the_user_is_discarded_and_becomes_a_clarification(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $otherUsersAccount = Account::factory()->create();

        $this->fakeGemini([
            'clarification' => null,
            'actions' => [
                [
                    'client_id' => 'a1',
                    'kind' => 'create_transaction',
                    'summary' => 'Despesa',
                    'transaction' => [
                        'account_id' => $otherUsersAccount->id,
                        'account_ref' => null,
                        'category_id' => null,
                        'type' => 'expense',
                        'entry_type' => 'single',
                        'description' => 'Teste',
                        'amount' => '10.00',
                        'due_date' => '2026-08-05',
                        'notes' => null,
                    ],
                ],
            ],
        ]);

        $result = app(AiAssistantService::class)->quickAdd('gastei 10 reais', $user);

        $this->assertSame([], $result['actions']);
        $this->assertNotNull($result['clarification']);
    }

    public function test_new_account_and_a_transaction_referencing_it_via_account_ref_resolve_together(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->fakeGemini([
            'clarification' => null,
            'actions' => [
                [
                    'client_id' => 'a1',
                    'kind' => 'create_account',
                    'summary' => 'Novo cartão Nubank com limite de R$ 5.000,00',
                    'account' => [
                        'name' => 'Nubank',
                        'type' => 'credit_card',
                        'initial_balance' => '0.00',
                        'credit_limit' => '5000.00',
                        'color' => null,
                    ],
                ],
                [
                    'client_id' => 'a2',
                    'kind' => 'create_transaction',
                    'summary' => 'Despesa de R$ 50,00 no Nubank',
                    'transaction' => [
                        'account_id' => null,
                        'account_ref' => 'a1',
                        'category_id' => null,
                        'type' => 'expense',
                        'entry_type' => 'single',
                        'description' => 'Compra',
                        'amount' => '50.00',
                        'due_date' => '2026-08-05',
                        'notes' => null,
                    ],
                ],
            ],
        ]);

        $result = app(AiAssistantService::class)->quickAdd(
            'cria o cartão Nubank com limite de 5000 e uma despesa de 50 nele',
            $user,
        );

        $this->assertCount(2, $result['actions']);
        $this->assertSame('account', $result['actions'][0]['kind']);
        $this->assertSame(500000, $result['actions'][0]['payload']['credit_limit_cents']);
        $this->assertSame('transaction', $result['actions'][1]['kind']);
        $this->assertNull($result['actions'][1]['payload']['account_id']);
        $this->assertSame('a1', $result['actions'][1]['account_ref']);
    }

    public function test_amount_without_decimal_places_is_discarded_instead_of_inflated_100x(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $account = Account::factory()->for($user)->create(['type' => AccountType::CREDIT_CARD]);

        $this->fakeGemini([
            'clarification' => null,
            'actions' => [
                [
                    'client_id' => 'a1',
                    'kind' => 'create_transaction',
                    'summary' => 'Despesa de R$ 140,00',
                    'transaction' => [
                        'account_id' => $account->id,
                        'account_ref' => null,
                        'category_id' => null,
                        'type' => 'expense',
                        'entry_type' => 'single',
                        'description' => 'Conta',
                        'amount' => '14000',
                        'due_date' => '2026-08-05',
                        'notes' => null,
                    ],
                ],
            ],
        ]);

        $result = app(AiAssistantService::class)->quickAdd('adicionei uma conta no valor de 140 reais', $user);

        $this->assertSame([], $result['actions']);
        $this->assertNotNull($result['clarification']);
    }

    public function test_unexpected_finish_reason_becomes_a_clarification_instead_of_an_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->fakeGemini(['clarification' => null, 'actions' => []], finishReason: 'SAFETY');

        $result = app(AiAssistantService::class)->quickAdd('qualquer coisa', $user);

        $this->assertSame([], $result['actions']);
        $this->assertNotNull($result['clarification']);
    }

    public function test_network_error_becomes_a_friendly_clarification_instead_of_a_500(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake(function (): void {
            throw new RuntimeException('connection timed out');
        });

        $result = app(AiAssistantService::class)->quickAdd('qualquer coisa', $user);

        $this->assertSame([], $result['actions']);
        $this->assertNotNull($result['clarification']);
    }
}
