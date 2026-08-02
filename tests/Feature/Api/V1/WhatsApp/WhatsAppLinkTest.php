<?php

namespace Tests\Feature\Api\V1\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_generate_a_link_code(): void
    {
        $this->postJson('/api/v1/whatsapp/link-code')->assertUnauthorized();
    }

    public function test_guest_cannot_unlink(): void
    {
        $this->deleteJson('/api/v1/whatsapp/link')->assertUnauthorized();
    }

    public function test_user_can_generate_a_six_digit_link_code(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/whatsapp/link-code');

        $response->assertOk()->assertJsonStructure(['code', 'expires_at']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $response->json('code'));
    }

    public function test_unlinking_clears_the_whatsapp_number_and_resets_the_session(): void
    {
        $user = User::factory()->create(['whatsapp_number' => '+5511999999999']);
        $session = WhatsAppSession::factory()->for($user)->awaitingConfirmation([['client_id' => 'a1']])->create([
            'phone_number' => '+5511999999999',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/whatsapp/link')->assertNoContent();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'whatsapp_number' => null]);
        $session->refresh();
        $this->assertSame('idle', $session->state->value);
        $this->assertNull($session->context);
    }
}
