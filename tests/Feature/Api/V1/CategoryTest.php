<?php

namespace Tests\Feature\Api\V1;

use App\Enum\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_category(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Alimentação',
            'type' => TransactionType::EXPENSE->value,
        ]);

        $response->assertCreated()->assertJsonPath('name', 'Alimentação');
    }

    public function test_user_only_sees_their_own_categories(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Category::factory()->for($userA)->create(['name' => 'Categoria A']);
        Category::factory()->for($userB)->create(['name' => 'Categoria B']);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_can_filter_categories_by_type(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->income()->create(['name' => 'Salário']);
        Category::factory()->for($user)->expense()->create(['name' => 'Aluguel']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/categories?type=income');

        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['name' => 'Salário']);
    }

    public function test_user_cannot_delete_another_users_category(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $category = Category::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $this->deleteJson("/api/v1/categories/{$category->id}")->assertNotFound();
    }
}
