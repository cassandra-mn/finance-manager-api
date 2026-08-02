<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Cliente HTTP para a WhatsApp Cloud API (Meta), usado para enviar as
 * respostas do bot: texto simples, menus (interactive list) e prompts de
 * confirmação (interactive buttons, no máximo 3 por mensagem).
 */
final class WhatsAppClient
{
    public function sendText(string $to, string $body): void
    {
        $this->send($to, [
            'type' => 'text',
            'text' => ['body' => $body, 'preview_url' => false],
        ]);
    }

    /**
     * @param  array<int, array{title: string, rows: array<int, array{id: string, title: string, description?: string}>}>  $sections
     */
    public function sendInteractiveList(string $to, string $bodyText, array $sections, ?string $header = null, ?string $footer = null): void
    {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $bodyText],
            'action' => [
                'button' => 'Escolher',
                'sections' => $sections,
            ],
        ];

        if ($header !== null) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        if ($footer !== null) {
            $interactive['footer'] = ['text' => $footer];
        }

        $this->send($to, ['type' => 'interactive', 'interactive' => $interactive]);
    }

    /** @param  array<int, array{id: string, title: string}>  $buttons */
    public function sendInteractiveButtons(string $to, string $bodyText, array $buttons): void
    {
        if (count($buttons) > 3) {
            throw new InvalidArgumentException('Uma mensagem de botões suporta no máximo 3 opções.');
        }

        $this->send($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => [
                    'buttons' => array_map(
                        static fn (array $button): array => ['type' => 'reply', 'reply' => $button],
                        $buttons,
                    ),
                ],
            ],
        ]);
    }

    /** @param  array<string, mixed>  $messageFields */
    private function send(string $to, array $messageFields): void
    {
        $baseUrl = rtrim((string) config('whatsapp.base_url'), '/');
        $apiVersion = (string) config('whatsapp.api_version');
        $phoneNumberId = (string) config('whatsapp.phone_number_id');

        try {
            $response = Http::withToken((string) config('whatsapp.access_token'))
                ->timeout(10)
                ->retry(2, 300, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false)
                ->post("{$baseUrl}/{$apiVersion}/{$phoneNumberId}/messages", array_merge([
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                ], $messageFields));
        } catch (Throwable $e) {
            Log::error('finance.whatsapp.send_failed', ['to' => $to, 'message' => $e->getMessage()]);

            throw new ServiceException('Não foi possível enviar a mensagem no WhatsApp.', previous: $e);
        }

        if ($response->failed()) {
            Log::error('finance.whatsapp.send_failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ServiceException('Não foi possível enviar a mensagem no WhatsApp.');
        }
    }
}
