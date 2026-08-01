<?php

namespace Tests\Feature\Api\V1\WhatsApp;

use App\Enum\WhatsAppSessionState;
use App\Models\Account;
use App\Models\User;
use App\Models\WhatsAppSession;
use App\Services\WhatsAppLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';

    private const VERIFY_TOKEN = 'test-verify-token';

    private const SENDER = '5511999999999';

    private const SENDER_E164 = '+5511999999999';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('whatsapp.app_secret', self::APP_SECRET);
        Config::set('whatsapp.verify_token', self::VERIFY_TOKEN);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.FAKE']]], 200),
        ]);
    }

    public function test_verification_handshake_succeeds_with_the_correct_token(): void
    {
        $response = $this->get('/api/v1/whatsapp/webhook?hub_mode=subscribe&hub_verify_token='.self::VERIFY_TOKEN.'&hub_challenge=12345');

        $response->assertOk()->assertContent('12345');
    }

    public function test_verification_handshake_fails_with_the_wrong_token(): void
    {
        $response = $this->get('/api/v1/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345');

        $response->assertForbidden();
    }

    public function test_post_without_a_signature_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/whatsapp/webhook', $this->textMessagePayload(self::SENDER, 'menu'));

        $response->assertUnauthorized();
    }

    public function test_post_with_an_invalid_signature_is_rejected(): void
    {
        $body = json_encode($this->textMessagePayload(self::SENDER, 'menu'));

        $response = $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalid',
        ], $body);

        $response->assertUnauthorized();
    }

    public function test_unlinked_sender_with_ordinary_text_is_told_to_link(): void
    {
        $this->postWebhook($this->textMessagePayload(self::SENDER, 'oi'))->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
            && str_contains(json_encode($request->data()), 'vinculado'));
    }

    public function test_unlinked_sender_with_a_valid_code_gets_linked(): void
    {
        $user = User::factory()->create();
        ['code' => $code] = app(WhatsAppLinkService::class)->generateLinkCode($user);

        $this->postWebhook($this->textMessagePayload(self::SENDER, $code))->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'whatsapp_number' => self::SENDER_E164]);
        $this->assertDatabaseHas('whatsapp_sessions', ['phone_number' => self::SENDER_E164, 'user_id' => $user->id]);
    }

    public function test_unlinked_sender_with_an_invalid_code_is_not_linked(): void
    {
        $user = User::factory()->create();

        $this->postWebhook($this->textMessagePayload(self::SENDER, '000000'))->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id, 'whatsapp_number' => self::SENDER_E164]);
    }

    public function test_linked_sender_sending_menu_receives_the_interactive_list(): void
    {
        User::factory()->create(['whatsapp_number' => self::SENDER_E164]);

        $this->postWebhook($this->textMessagePayload(self::SENDER, 'menu'))->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'graph.facebook.com')
                && ($body['interactive']['type'] ?? null) === 'list';
        });
    }

    public function test_linked_sender_free_text_triggers_quick_add_and_awaits_confirmation(): void
    {
        $user = User::factory()->create(['whatsapp_number' => self::SENDER_E164]);
        $account = Account::factory()->for($user)->create();

        $this->fakeGemini([
            'clarification' => null,
            'actions' => [[
                'client_id' => 'a1',
                'kind' => 'create_transaction',
                'summary' => 'Despesa de R$ 50,00',
                'transaction' => [
                    'account_id' => $account->id,
                    'account_ref' => null,
                    'category_id' => null,
                    'type' => 'expense',
                    'entry_type' => 'single',
                    'description' => 'Mercado',
                    'amount' => '50.00',
                    'due_date' => '2026-08-05',
                    'notes' => null,
                ],
            ]],
        ]);

        $this->postWebhook($this->textMessagePayload(self::SENDER, 'gastei 50 reais no mercado'))->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'graph.facebook.com')
                && ($body['interactive']['type'] ?? null) === 'button';
        });

        $session = WhatsAppSession::query()->where('phone_number', self::SENDER_E164)->firstOrFail();
        $this->assertSame(WhatsAppSessionState::AWAITING_CONFIRMATION, $session->state);
        $this->assertNotEmpty($session->context);
    }

    public function test_confirming_a_pending_action_creates_the_transaction(): void
    {
        $user = User::factory()->create(['whatsapp_number' => self::SENDER_E164]);
        $account = Account::factory()->for($user)->create();

        WhatsAppSession::factory()->for($user)->awaitingConfirmation([[
            'client_id' => 'a1',
            'kind' => 'transaction',
            'summary' => 'Despesa de R$ 50,00',
            'account_ref' => null,
            'payload' => [
                'account_id' => $account->id,
                'category_id' => null,
                'type' => 'expense',
                'entry_type' => 'single',
                'description' => 'Mercado',
                'amount_cents' => 5000,
                'due_date' => '2026-08-05',
                'notes' => null,
            ],
        ]])->create(['phone_number' => self::SENDER_E164]);

        $this->postWebhook($this->interactiveReplyPayload(self::SENDER, 'button_reply', 'confirm_yes'))->assertOk();

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'description' => 'Mercado',
            'amount_cents' => 5000,
        ]);

        $session = WhatsAppSession::query()->where('phone_number', self::SENDER_E164)->firstOrFail();
        $this->assertSame(WhatsAppSessionState::IDLE, $session->state);
        $this->assertNull($session->context);
    }

    public function test_cancelling_a_pending_action_creates_nothing(): void
    {
        $user = User::factory()->create(['whatsapp_number' => self::SENDER_E164]);
        $account = Account::factory()->for($user)->create();

        WhatsAppSession::factory()->for($user)->awaitingConfirmation([[
            'client_id' => 'a1',
            'kind' => 'transaction',
            'summary' => 'Despesa de R$ 50,00',
            'account_ref' => null,
            'payload' => [
                'account_id' => $account->id,
                'category_id' => null,
                'type' => 'expense',
                'entry_type' => 'single',
                'description' => 'Mercado',
                'amount_cents' => 5000,
                'due_date' => '2026-08-05',
                'notes' => null,
            ],
        ]])->create(['phone_number' => self::SENDER_E164]);

        $this->postWebhook($this->interactiveReplyPayload(self::SENDER, 'button_reply', 'confirm_no'))->assertOk();

        $this->assertDatabaseMissing('transactions', ['description' => 'Mercado']);

        $session = WhatsAppSession::query()->where('phone_number', self::SENDER_E164)->firstOrFail();
        $this->assertSame(WhatsAppSessionState::IDLE, $session->state);
    }

    public function test_rate_limiter_blocks_after_too_many_requests_in_a_minute_from_the_same_sender(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postWebhook($this->textMessagePayload(self::SENDER, "mensagem {$i}"))->assertOk();
        }

        $this->postWebhook($this->textMessagePayload(self::SENDER, 'mensagem extra'))->assertTooManyRequests();
    }

    /** @param  array<string, mixed>  $structured */
    private function fakeGemini(array $structured): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.FAKE']]], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($structured)]]],
                    'finishReason' => 'STOP',
                ]],
            ], 200),
        ]);
    }

    private function postWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, self::APP_SECRET);

        return $this->call('POST', '/api/v1/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body);
    }

    private function textMessagePayload(string $from, string $body): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '15550000000', 'phone_number_id' => 'PHONE_ID'],
                        'contacts' => [['profile' => ['name' => 'Ana'], 'wa_id' => $from]],
                        'messages' => [[
                            'from' => $from,
                            'id' => 'wamid.'.uniqid(),
                            'timestamp' => (string) now()->timestamp,
                            'text' => ['body' => $body],
                            'type' => 'text',
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];
    }

    private function interactiveReplyPayload(string $from, string $type, string $id): array
    {
        $interactive = $type === 'list_reply'
            ? ['type' => 'list_reply', 'list_reply' => ['id' => $id, 'title' => $id]]
            : ['type' => 'button_reply', 'button_reply' => ['id' => $id, 'title' => $id]];

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'messages' => [[
                            'from' => $from,
                            'id' => 'wamid.'.uniqid(),
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'interactive',
                            'interactive' => $interactive,
                        ]],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];
    }
}
