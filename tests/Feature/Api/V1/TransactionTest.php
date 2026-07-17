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
}
