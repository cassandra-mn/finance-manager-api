<?php

namespace Tests\Feature\Services;

use App\Enum\WhatsAppSessionState;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppBotServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SENDER = '5511999999999';

    private const SENDER_E164 = '+5511999999999';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.FAKE']]], 200),
        ]);
    }

    public function test_menu_trigger_words_send_the_interactive_list(): void
    {
        User::factory()->create(['whatsapp_number' => self::SENDER_E164]);

        app(WhatsAppBotService::class)->handleIncomingMessage(self::SENDER, 'text', 'ajuda', null);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
            && ($request->data()['interactive']['type'] ?? null) === 'list');
    }

    public function test_a_stale_awaiting_confirmation_session_is_reset_before_processing_a_new_message(): void
    {
        Carbon::setTestNow(now()->subMinutes(30));

        $user = User::factory()->create(['whatsapp_number' => self::SENDER_E164]);
        WhatsAppSession::factory()->for($user)->awaitingConfirmation([['client_id' => 'a1', 'summary' => 'x']])->create([
            'phone_number' => self::SENDER_E164,
        ]);

        Carbon::setTestNow();

        app(WhatsAppBotService::class)->handleIncomingMessage(self::SENDER, 'text', 'menu', null);

        $session = WhatsAppSession::query()->where('phone_number', self::SENDER_E164)->firstOrFail();
        $this->assertSame(WhatsAppSessionState::IDLE, $session->state);
        $this->assertNull($session->context);

        Carbon::setTestNow();
    }

    public function test_a_recently_awaiting_confirmation_session_is_not_reset(): void
    {
        $user = User::factory()->create(['whatsapp_number' => self::SENDER_E164]);
        WhatsAppSession::factory()->for($user)->awaitingConfirmation([['client_id' => 'a1', 'summary' => 'x']])->create([
            'phone_number' => self::SENDER_E164,
        ]);

        app(WhatsAppBotService::class)->handleIncomingMessage(self::SENDER, 'text', 'menu', null);

        $session = WhatsAppSession::query()->where('phone_number', self::SENDER_E164)->firstOrFail();
        $this->assertSame(WhatsAppSessionState::AWAITING_CONFIRMATION, $session->state);
    }

    public function test_a_session_linked_to_a_deleted_user_is_treated_as_unlinked(): void
    {
        $user = User::factory()->create(['whatsapp_number' => self::SENDER_E164]);
        $userId = $user->id;
        WhatsAppSession::factory()->create(['phone_number' => self::SENDER_E164, 'user_id' => $userId]);
        $user->forceDelete();

        app(WhatsAppBotService::class)->handleIncomingMessage(self::SENDER, 'text', 'oi', null);

        $session = WhatsAppSession::query()->where('phone_number', self::SENDER_E164)->firstOrFail();
        $this->assertNull($session->user_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_non_text_message_without_interactive_reply_gets_a_friendly_fallback(): void
    {
        User::factory()->create(['whatsapp_number' => self::SENDER_E164]);

        app(WhatsAppBotService::class)->handleIncomingMessage(self::SENDER, 'image', null, null);

        Http::assertSent(fn ($request) => str_contains($request->data()['text']['body'] ?? '', 'só consigo processar'));
    }
}
