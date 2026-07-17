<?php

namespace Tests\Feature\Api\V1;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_transaction_as_paid_sets_status_and_paid_at(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/pay");

        $response->assertOk()->assertJsonPath('status', 'paid');
        $this->assertNotNull($transaction->fresh()->paid_at);
    }

    public function test_cannot_pay_a_cancelled_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create(['status' => 'cancelled']);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/transactions/{$transaction->id}/pay")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_cancelling_a_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/transactions/{$transaction->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }
}
