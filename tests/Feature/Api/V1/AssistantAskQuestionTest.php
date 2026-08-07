<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantAskQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_the_ask_endpoint(): void
    {
        $this->postJson('/api/v1/assistant/ask', ['message' => 'oi'])->assertUnauthorized();
    }

    public function test_message_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/assistant/ask', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_message_cannot_exceed_500_characters(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/assistant/ask', ['message' => str_repeat('a', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_user_gets_an_answer_to_a_financial_question(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => ['parts' => [['text' => json_encode(['answer' => 'Você não tem despesas registradas ainda.'])]]],
                        'finishReason' => 'STOP',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/assistant/ask', ['message' => 'qual a categoria com mais gasto?']);

        $response->assertOk()
            ->assertJsonPath('answer', 'Você não tem despesas registradas ainda.');
    }

    public function test_ask_and_quick_add_share_the_same_rate_limiter(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => ['parts' => [['text' => json_encode(['clarification' => null, 'actions' => [], 'answer' => 'ok'])]]],
                        'finishReason' => 'STOP',
                    ],
                ],
            ], 200),
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/assistant/quick-add', ['message' => "mensagem {$i}"])->assertOk();
        }

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/assistant/ask', ['message' => "pergunta {$i}"])->assertOk();
        }

        // The 6th call across either endpoint hits the shared per-user throttle.
        $this->postJson('/api/v1/assistant/ask', ['message' => 'pergunta extra'])->assertTooManyRequests();
    }
}
