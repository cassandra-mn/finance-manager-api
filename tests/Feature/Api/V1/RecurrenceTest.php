<?php

namespace Tests\Feature\Api\V1;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Recurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecurrenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_recurrence(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $account->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-05',
        ]);

        $response->assertCreated()
            ->assertJsonPath('description', 'Aluguel')
            ->assertJsonPath('amount_cents', 150000)
            ->assertJsonPath('frequency', 'monthly')
            ->assertJsonPath('frequency_label', 'Mensal')
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('category', null);

        $this->assertDatabaseHas('recurrences', [
            'user_id' => $user->id,
            'description' => 'Aluguel',
        ]);
    }

    public function test_user_can_create_a_recurrence_with_a_compatible_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->expense()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-05',
        ]);

        $response->assertCreated()->assertJsonPath('category.id', $category->id);
    }

    public function test_fails_when_category_type_is_incompatible(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $category = Category::factory()->for($user)->income()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-05',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    public function test_fails_with_another_users_account(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $accountFromB = Account::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $accountFromB->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-05',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['account_id']);
    }

    public function test_fails_with_another_users_category(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $account = Account::factory()->for($userA)->create();
        $categoryFromB = Category::factory()->for($userB)->expense()->create();

        Sanctum::actingAs($userA);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $account->id,
            'category_id' => $categoryFromB->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-05',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    public function test_fails_with_entry_type_single(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $account->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::SINGLE->value,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-05',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['entry_type']);
    }

    public function test_fails_when_amount_cents_is_zero_or_negative(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $account->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'description' => 'Aluguel',
            'amount_cents' => 0,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-05',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['amount_cents']);
    }

    public function test_fails_when_end_date_is_before_start_date(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $account->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-05',
            'end_date' => '2026-08-01',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
    }

    public function test_fails_when_next_due_date_is_before_start_date(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/recurrences', [
            'account_id' => $account->id,
            'type' => TransactionType::EXPENSE->value,
            'entry_type' => TransactionEntryType::FIXED->value,
            'description' => 'Aluguel',
            'amount_cents' => 150000,
            'frequency' => RecurrenceFrequency::MONTHLY->value,
            'start_date' => '2026-08-05',
            'next_due_date' => '2026-08-01',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['next_due_date']);
    }

    public function test_user_only_sees_their_own_recurrences(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Recurrence::factory()->for($userA)->create();
        Recurrence::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/recurrences');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_can_filter_recurrences_by_account_type_frequency_and_active_status(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->for($user)->create();
        $otherAccount = Account::factory()->for($user)->create();

        Recurrence::factory()->for($user)->for($account)->expense()
            ->create(['frequency' => RecurrenceFrequency::MONTHLY, 'description' => 'Aluguel']);
        Recurrence::factory()->for($user)->for($account)->income()
            ->create(['frequency' => RecurrenceFrequency::MONTHLY, 'description' => 'Salário']);
        Recurrence::factory()->for($user)->for($otherAccount)->expense()->paused()
            ->create(['frequency' => RecurrenceFrequency::WEEKLY, 'description' => 'Academia']);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/recurrences?account_id={$account->id}&type=expense");
        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['description' => 'Aluguel']);

        $response = $this->getJson('/api/v1/recurrences?frequency=weekly');
        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['description' => 'Academia']);

        $response = $this->getJson('/api/v1/recurrences?is_active=false');
        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['description' => 'Academia']);

        $response = $this->getJson('/api/v1/recurrences?search=sal');
        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['description' => 'Salário']);
    }

    public function test_user_can_update_a_recurrence(): void
    {
        $user = User::factory()->create();
        $recurrence = Recurrence::factory()->for($user)->create(['description' => 'Antigo']);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/v1/recurrences/{$recurrence->id}", [
            'description' => 'Novo',
            'amount_cents' => 99900,
        ]);

        $response->assertOk()
            ->assertJsonPath('description', 'Novo')
            ->assertJsonPath('amount_cents', 99900);
    }

    public function test_user_can_pause_an_active_recurrence(): void
    {
        $user = User::factory()->create();
        $recurrence = Recurrence::factory()->for($user)->create(['is_active' => true]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/recurrences/{$recurrence->id}/pause");

        $response->assertOk()->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('recurrences', ['id' => $recurrence->id, 'is_active' => false]);
    }

    public function test_user_can_resume_a_paused_recurrence(): void
    {
        $user = User::factory()->create();
        $recurrence = Recurrence::factory()->for($user)->paused()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/recurrences/{$recurrence->id}/resume");

        $response->assertOk()->assertJsonPath('is_active', true);
        $this->assertDatabaseHas('recurrences', ['id' => $recurrence->id, 'is_active' => true]);
    }

    public function test_cannot_pause_an_already_paused_recurrence(): void
    {
        $user = User::factory()->create();
        $recurrence = Recurrence::factory()->for($user)->paused()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/recurrences/{$recurrence->id}/pause");

        $response->assertUnprocessable()->assertJsonValidationErrors(['is_active']);
    }

    public function test_cannot_resume_an_already_active_recurrence(): void
    {
        $user = User::factory()->create();
        $recurrence = Recurrence::factory()->for($user)->create(['is_active' => true]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/recurrences/{$recurrence->id}/resume");

        $response->assertUnprocessable()->assertJsonValidationErrors(['is_active']);
    }

    public function test_user_cannot_access_another_users_recurrence(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $recurrence = Recurrence::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/recurrences/{$recurrence->id}")->assertNotFound();
        $this->patchJson("/api/v1/recurrences/{$recurrence->id}", ['description' => 'Hackeado'])->assertNotFound();
        $this->deleteJson("/api/v1/recurrences/{$recurrence->id}")->assertNotFound();
        $this->postJson("/api/v1/recurrences/{$recurrence->id}/pause")->assertNotFound();
        $this->postJson("/api/v1/recurrences/{$recurrence->id}/resume")->assertNotFound();
    }

    public function test_user_can_soft_delete_a_recurrence(): void
    {
        $user = User::factory()->create();
        $recurrence = Recurrence::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/recurrences/{$recurrence->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('recurrences', ['id' => $recurrence->id]);
        $this->getJson("/api/v1/recurrences/{$recurrence->id}")->assertNotFound();
    }
}
