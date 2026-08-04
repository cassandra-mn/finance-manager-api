<?php

namespace Tests\Feature\Api\V1;

use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::SINGLE->value,
            'description' => 'Supermercado',
            'amount_cents' => 15000,
            'due_date' => Carbon::today()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('description', 'Supermercado')
            ->assertJsonPath('status', 'pending');
    }

    public function test_user_cannot_reference_another_users_account(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountFromB = Account::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/v1/transactions', [
            'account_id' => $accountFromB->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::SINGLE->value,
            'description' => 'Supermercado',
            'amount_cents' => 15000,
            'due_date' => Carbon::today()->toDateString(),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['account_id']);
    }

    public function test_listing_transactions_does_not_crash_when_the_account_was_soft_deleted(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        $transaction = \App\Models\Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => TransactionType::EXPENSE->value,
        ]);

        // Deleting an account does not currently block deletion when it still
        // has transactions attached (soft delete), which leaves this
        // transaction's account relation resolving to null.
        $account->delete();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transactions');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $transaction->id)
            ->assertJsonPath('data.0.account', null);
    }
}
