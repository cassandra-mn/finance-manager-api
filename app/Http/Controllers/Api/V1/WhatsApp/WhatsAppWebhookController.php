<?php

namespace App\Http\Controllers\Api\V1\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Recebe o handshake de verificação (GET, exigido pela Meta ao cadastrar o
 * webhook) e as mensagens recebidas (POST, assinadas via HMAC-SHA256 em
 * X-Hub-Signature-256). Nunca confia no corpo sem verificar a assinatura.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppBotService $botService,
    ) {}

    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        if ($mode === 'subscribe' && hash_equals((string) config('whatsapp.verify_token'), $token)) {
            return response($challenge, Response::HTTP_OK);
        }

        return response('Forbidden', Response::HTTP_FORBIDDEN);
    }

    public function handle(Request $request): JsonResponse
    {
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) config('whatsapp.app_secret'));

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            Log::warning('finance.whatsapp.invalid_signature');

            return response()->json(['message' => 'Assinatura inválida.'], Response::HTTP_UNAUTHORIZED);
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $messages = $change['value']['messages'] ?? [];

                foreach ($messages as $message) {
                    $this->botService->handleIncomingMessage(
                        waId: $message['from'],
                        messageType: $message['type'],
                        text: $message['text']['body'] ?? null,
                        interactiveReplyId: $message['interactive']['list_reply']['id']
                            ?? $message['interactive']['button_reply']['id']
                            ?? null,
                    );
                }
            }
        }

        return response()->json(['message' => 'ok']);
    }
}
