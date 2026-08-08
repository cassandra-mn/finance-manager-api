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

    public function test_paying_with_an_amount_lower_than_the_total_registers_a_partial_payment(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create(['amount_cents' => 20000]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/transactions/{$transaction->id}/pay", ['amount_cents' => 5000]);

        $response->assertOk()
            ->assertJsonPath('status', 'partially_paid')
            ->assertJsonPath('amount_cents', 20000)
            ->assertJsonPath('paid_amount_cents', 5000);

        $fresh = $transaction->fresh();
        $this->assertSame('partially_paid', $fresh->status->value);
        $this->assertSame(5000, $fresh->paid_amount_cents);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_paying_with_the_full_amount_explicitly_is_a_normal_full_payment(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create(['amount_cents' => 20000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/transactions/{$transaction->id}/pay", ['amount_cents' => 20000])
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('paid_amount_cents', 20000);
    }

    public function test_cannot_pay_a_transaction_with_more_than_its_total_amount(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($user)->for($account)->create(['amount_cents' => 20000]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/transactions/{$transaction->id}/pay", ['amount_cents' => 20001])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_cents']);
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
