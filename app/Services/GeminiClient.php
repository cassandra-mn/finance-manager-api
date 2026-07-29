<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GeminiClient
{
    /**
     * Chama o endpoint generateContent do Gemini pedindo JSON estruturado de acordo
     * com o responseSchema (formato OpenAPI-subset do Gemini) e devolve o payload decodificado.
     *
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     */
    public function generateStructured(string $systemInstruction, string $userMessage, array $responseSchema): array
    {
        $model = (string) config('services.gemini.model');
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => (string) config('services.gemini.key'),
                'Content-Type' => 'application/json',
            ])
                ->timeout(20)
                ->retry(2, 300, fn (Throwable $e): bool => $e instanceof ConnectionException, throw: false)
                ->post("{$baseUrl}/v1beta/models/{$model}:generateContent", [
                    'system_instruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $userMessage]]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $responseSchema,
                        'temperature' => 0,
                        'maxOutputTokens' => 2048,
                    ],
                ]);
        } catch (Throwable $e) {
            Log::error('finance.assistant.gemini_request_failed', ['message' => $e->getMessage()]);

            throw new ServiceException('Assistente indisponível no momento, tente novamente.', previous: $e);
        }

        if ($response->failed()) {
            Log::error('finance.assistant.gemini_response_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ServiceException('Assistente indisponível no momento, tente novamente.');
        }

        $finishReason = $response->json('candidates.0.finishReason');

        if ($finishReason !== 'STOP') {
            Log::warning('finance.assistant.gemini_unexpected_finish_reason', ['finish_reason' => $finishReason]);

            throw new ServiceException('Não consegui interpretar o pedido, pode detalhar de novo?');
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new ServiceException('Não consegui interpretar o pedido, pode detalhar de novo?');
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            Log::warning('finance.assistant.gemini_invalid_json', ['text' => $text]);

            throw new ServiceException('Não consegui interpretar o pedido, pode detalhar de novo?');
        }

        return $decoded;
    }
}
