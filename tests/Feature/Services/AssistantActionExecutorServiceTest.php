<?php

namespace Tests\Feature\Services;

use App\Enum\TransactionStatus;
use App\Exceptions\ServiceException;
use App\Models\Account;
use App\Models\User;
use App\Services\AssistantActionExecutorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantActionExecutorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_executes_a_single_account_action(): void
    {
        $user = User::factory()->create();

        $results = app(AssistantActionExecutorService::class)->execute([
            [
                'client_id' => 'a1',
                'kind' => 'account',
                'summary' => 'Nova conta Nubank',
                'account_ref' => null,
                'payload' => [
                    'name' => 'Nubank',
                    'type' => 'credit_card',
                    'initial_balance_cents' => 0,
                    'credit_limit_cents' => 500000,
                    'color' => null,
                ],
            ],
        ], $user);

        $this->assertCount(1, $results);
        $this->assertSame('account', $results[0]['kind']);
        $this->assertDatabaseHas('accounts', ['id' => $results[0]['id'], 'user_id' => $user->id, 'name' => 'Nubank']);
    }

    public function test_executes_a_single_transaction_action_against_an_existing_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $results = app(AssistantActionExecutorService::class)->execute([
            $this->transactionAction('a1', $account->id, null),
        ], $user);

        $this->assertCount(1, $results);
        $this->assertDatabaseHas('transactions', [
            'id' => $results[0]['id'],
            'account_id' => $account->id,
            'user_id' => $user->id,
            'status' => TransactionStatus::PENDING->value,
            'amount_cents' => 5000,
        ]);
    }

    public function test_a_batch_with_an_account_and_a_transaction_referencing_it_resolves_together(): void
    {
        $user = User::factory()->create();

        $results = app(AssistantActionExecutorService::class)->execute([
            [
                'client_id' => 'a1',
                'kind' => 'account',
                'summary' => 'Nova conta',
                'account_ref' => null,
                'payload' => [
                    'name' => 'Nubank',
                    'type' => 'credit_card',
                    'initial_balance_cents' => 0,
                    'credit_limit_cents' => null,
                    'color' => null,
                ],
            ],
            $this->transactionAction('a2', null, 'a1'),
        ], $user);

        $this->assertCount(2, $results);
        $accountId = $results[0]['id'];
        $this->assertDatabaseHas('transactions', ['id' => $results[1]['id'], 'account_id' => $accountId]);
    }

    public function test_a_failure_in_the_batch_rolls_back_everything(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        $this->expectException(ServiceException::class);

        try {
            app(AssistantActionExecutorService::class)->execute([
                $this->transactionAction('a1', $account->id, null),
                $this->transactionAction('a2', 999999, null),
            ], $user);
        } finally {
            $this->assertDatabaseCount('transactions', 0);
        }
    }

    public function test_a_transaction_referencing_an_unresolved_account_ref_fails(): void
    {
        $user = User::factory()->create();

        $this->expectException(ServiceException::class);

        app(AssistantActionExecutorService::class)->execute([
            $this->transactionAction('a1', null, 'does-not-exist'),
        ], $user);
    }

    /** @return array<string, mixed> */
    private function transactionAction(string $clientId, ?int $accountId, ?string $accountRef): array
    {
        return [
            'client_id' => $clientId,
            'kind' => 'transaction',
            'summary' => 'Despesa de R$ 50,00',
            'account_ref' => $accountRef,
            'payload' => [
                'account_id' => $accountId,
                'category_id' => null,
                'type' => 'expense',
                'entry_type' => 'single',
                'description' => 'Mercado',
                'amount_cents' => 5000,
                'due_date' => '2026-08-05',
                'notes' => null,
            ],
        ];
    }
}
