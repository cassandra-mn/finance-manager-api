<?php

namespace Tests\Feature\Api\V1;

use App\Enum\AccountType;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\TransactionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionGroupTest extends TestCase
{
    use RefreshDatabase;

    private function creditCardAccount(User $user, int $dueDay = 5, int $closingDay = 25): Account
    {
        return Account::factory()->for($user)->creditCard($dueDay, $closingDay)->create(['name' => 'Nubank']);
    }

    private function createTransaction(User $user, Account $account, string $dueDate, int $amountCents = 10000): void
    {
        $this->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::SINGLE->value,
            'description' => 'Compra',
            'amount_cents' => $amountCents,
            'due_date' => $dueDate,
        ])->assertCreated();
    }

    public function test_credit_card_purchases_in_the_same_cycle_share_the_same_invoice(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10', 10000);
        $this->createTransaction($user, $account, '2026-07-15', 20000);

        $this->assertSame(1, TransactionGroup::query()->count());

        $group = TransactionGroup::query()->first();
        $this->assertSame('2026-08-01', $group->reference_month->toDateString());
        $this->assertSame('2026-07-25', $group->closing_date->toDateString());
        $this->assertSame('2026-08-05', $group->due_date->toDateString());
        $this->assertSame(30000, $group->total_cents);
        $this->assertSame(2, $group->transactions()->count());
    }

    public function test_purchase_after_closing_day_creates_a_new_invoice(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');
        $this->createTransaction($user, $account, '2026-07-30');

        $this->assertSame(2, TransactionGroup::query()->count());
    }

    public function test_regular_account_transactions_are_not_grouped(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');

        $this->assertSame(0, TransactionGroup::query()->count());
    }

    public function test_credit_card_without_invoice_due_day_configured_does_not_group_transactions(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create(['type' => AccountType::CREDIT_CARD]);
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');

        $this->assertSame(0, TransactionGroup::query()->count());
        $this->assertNull($account->transactions()->firstOrFail()->transaction_group_id);
    }

    public function test_created_transaction_exposes_the_group_and_effective_status(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::SINGLE->value,
            'description' => 'Compra',
            'amount_cents' => 10000,
            'due_date' => '2026-07-10',
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('transaction_group_id'));
        $this->assertSame('pending', $response->json('effective_status'));
        $this->assertSame('pending', $response->json('status'));
    }

    public function test_cannot_mark_a_grouped_transaction_as_paid_directly(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');
        $transaction = $account->transactions()->firstOrFail();

        $this->postJson("/api/v1/transactions/{$transaction->id}/pay")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_cancel_a_grouped_transaction_directly(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');
        $transaction = $account->transactions()->firstOrFail();

        $this->postJson("/api/v1/transactions/{$transaction->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_pay_an_open_invoice(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');
        $group = TransactionGroup::query()->firstOrFail();

        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", [
            'payment_account_id' => $checking->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_closing_and_paying_an_invoice_creates_the_settlement_transaction(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10', 10000);
        $this->createTransaction($user, $account, '2026-07-15', 20000);
        $group = TransactionGroup::query()->firstOrFail();

        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")
            ->assertOk()
            ->assertJsonPath('status', 'closed');

        $response = $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", [
            'payment_account_id' => $checking->id,
            'paid_at' => '2026-08-05',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('total_cents', 30000);

        $this->assertDatabaseHas('transactions', [
            'account_id' => $checking->id,
            'amount_cents' => 30000,
            'status' => 'paid',
            'transaction_group_id' => null,
        ]);

        $group->refresh();
        $this->assertTrue($group->isPaid());
        $this->assertSame($checking->id, $group->payment_account_id);
        $this->assertNotNull($group->payment_transaction_id);
    }

    public function test_paying_an_invoice_with_an_amount_lower_than_the_total_registers_a_partial_payment(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10', 10000);
        $this->createTransaction($user, $account, '2026-07-15', 20000);
        $group = TransactionGroup::query()->firstOrFail();

        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertOk();

        $response = $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", [
            'payment_account_id' => $checking->id,
            'paid_at' => '2026-08-05',
            'amount_cents' => 12000,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'partially_paid')
            ->assertJsonPath('total_cents', 30000)
            ->assertJsonPath('paid_amount_cents', 12000);

        // A transação de liquidação debita só o valor efetivamente pago —
        // os 18000 restantes não viram uma nova pendência no app.
        $this->assertDatabaseHas('transactions', [
            'account_id' => $checking->id,
            'amount_cents' => 12000,
            'status' => 'paid',
            'transaction_group_id' => null,
        ]);

        $group->refresh();
        $this->assertTrue($group->isPartiallyPaid());
        $this->assertSame(12000, $group->paid_amount_cents);
    }

    public function test_cannot_pay_an_already_partially_paid_invoice_again(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10', 10000);
        $group = TransactionGroup::query()->firstOrFail();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertOk();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", [
            'payment_account_id' => $checking->id,
            'amount_cents' => 5000,
        ])->assertOk();

        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", ['payment_account_id' => $checking->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_pay_an_invoice_with_more_than_its_total_amount(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10', 10000);
        $group = TransactionGroup::query()->firstOrFail();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertOk();

        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", [
            'payment_account_id' => $checking->id,
            'amount_cents' => 10001,
        ])->assertUnprocessable()->assertJsonValidationErrors(['amount_cents']);
    }

    public function test_cannot_pay_an_invoice_twice(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');
        $group = TransactionGroup::query()->firstOrFail();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertOk();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", ['payment_account_id' => $checking->id])->assertOk();

        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", ['payment_account_id' => $checking->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cannot_edit_or_delete_a_transaction_from_a_paid_invoice(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');
        $transaction = $account->transactions()->firstOrFail();
        $group = TransactionGroup::query()->firstOrFail();

        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertOk();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", ['payment_account_id' => $checking->id])->assertOk();

        $this->patchJson("/api/v1/transactions/{$transaction->id}", ['amount_cents' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_group_id']);

        $this->deleteJson("/api/v1/transactions/{$transaction->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_group_id']);
    }

    public function test_user_can_delete_an_open_invoice_and_its_transactions(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10', 10000);
        $this->createTransaction($user, $account, '2026-07-15', 20000);
        $group = TransactionGroup::query()->firstOrFail();
        $transactionIds = $group->transactions()->pluck('id');

        $this->deleteJson("/api/v1/transaction-groups/{$group->id}")->assertNoContent();

        $this->assertSoftDeleted('transaction_groups', ['id' => $group->id]);
        foreach ($transactionIds as $transactionId) {
            $this->assertSoftDeleted('transactions', ['id' => $transactionId]);
        }
    }

    public function test_cannot_delete_a_paid_invoice(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');
        $group = TransactionGroup::query()->firstOrFail();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertOk();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", ['payment_account_id' => $checking->id])->assertOk();

        $this->deleteJson("/api/v1/transaction-groups/{$group->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('transaction_groups', ['id' => $group->id, 'deleted_at' => null]);
    }

    public function test_cannot_delete_a_partially_paid_invoice(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10');
        $group = TransactionGroup::query()->firstOrFail();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertOk();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", [
            'payment_account_id' => $checking->id,
            'amount_cents' => 5000,
        ])->assertOk();

        $this->deleteJson("/api/v1/transaction-groups/{$group->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('transaction_groups', ['id' => $group->id, 'deleted_at' => null]);
    }

    public function test_user_cannot_delete_another_users_invoice(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = $this->creditCardAccount($userB);

        Sanctum::actingAs($userB);
        $this->createTransaction($userB, $accountB, '2026-07-10');
        $group = TransactionGroup::query()->firstOrFail();

        Sanctum::actingAs($userA);
        $this->deleteJson("/api/v1/transaction-groups/{$group->id}")->assertNotFound();
    }

    public function test_cannot_edit_or_delete_a_transaction_from_a_partially_paid_invoice(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        $checking = Account::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10', 10000);
        $transaction = $account->transactions()->firstOrFail();
        $group = TransactionGroup::query()->firstOrFail();

        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertOk();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/pay", [
            'payment_account_id' => $checking->id,
            'amount_cents' => 5000,
        ])->assertOk();

        $this->patchJson("/api/v1/transactions/{$transaction->id}", ['amount_cents' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_group_id']);

        $this->deleteJson("/api/v1/transactions/{$transaction->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_group_id']);
    }

    public function test_user_can_list_and_show_their_invoices(): void
    {
        $user = User::factory()->create();
        $account = $this->creditCardAccount($user);
        Sanctum::actingAs($user);

        $this->createTransaction($user, $account, '2026-07-10', 10000);
        $group = TransactionGroup::query()->firstOrFail();

        $this->getJson('/api/v1/transaction-groups')->assertOk()->assertJsonCount(1);

        $this->getJson("/api/v1/transaction-groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('id', $group->id)
            ->assertJsonCount(1, 'transactions');
    }

    public function test_user_cannot_access_another_users_invoice(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = $this->creditCardAccount($userB);

        Sanctum::actingAs($userB);
        $this->createTransaction($userB, $accountB, '2026-07-10');
        $group = TransactionGroup::query()->firstOrFail();

        Sanctum::actingAs($userA);
        $this->getJson("/api/v1/transaction-groups/{$group->id}")->assertNotFound();
        $this->postJson("/api/v1/transaction-groups/{$group->id}/close")->assertNotFound();
    }
}
