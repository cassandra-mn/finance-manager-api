<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantQuickAddTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_quick_add_endpoint(): void
    {
        $this->postJson('/api/v1/assistant/quick-add', ['message' => 'oi'])->assertUnauthorized();
    }

    public function test_message_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/assistant/quick-add', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_message_cannot_exceed_500_characters(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/assistant/quick-add', ['message' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_user_can_get_a_quick_add_proposal(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => ['parts' => [['text' => json_encode(['clarification' => 'Pode detalhar melhor?', 'actions' => []])]]],
                        'finishReason' => 'STOP',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/assistant/quick-add', ['message' => 'gastei um dinheiro em algum lugar']);

        $response->assertOk()
            ->assertJsonPath('clarification', 'Pode detalhar melhor?')
            ->assertJsonPath('actions', []);
    }

    public function test_rate_limiter_blocks_after_too_many_requests_in_a_minute(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => ['parts' => [['text' => json_encode(['clarification' => null, 'actions' => []])]]],
                        'finishReason' => 'STOP',
                    ],
                ],
            ], 200),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/assistant/quick-add', ['message' => "mensagem {$i}"])->assertOk();
        }

        $this->postJson('/api/v1/assistant/quick-add', ['message' => 'mensagem extra'])->assertTooManyRequests();
    }
}
