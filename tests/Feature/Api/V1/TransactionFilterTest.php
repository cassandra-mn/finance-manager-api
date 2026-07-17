<?php

namespace Tests\Feature\Api\V1;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_transactions_are_flagged_via_display_status(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        Transaction::factory()->for($user)->for($account)->overdue()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transactions?status=overdue');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_user_cannot_see_another_users_transactions(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountB = Account::factory()->for($userB)->create();
        Transaction::factory()->for($userB)->for($accountB)->create();

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/transactions');

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filters_by_account_and_type(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($account)->income()->create();
        Transaction::factory()->for($user)->for($account)->expense()->create();
        Transaction::factory()->for($user)->for($otherAccount)->income()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/transactions?account_id={$account->id}&type=income");

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
