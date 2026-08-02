<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\WhatsAppSessionRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Vincula um número de WhatsApp a um usuário via um código numérico de curta
 * duração: o usuário gera o código autenticado no app e o envia como
 * mensagem de texto ao bot, que o resolve de volta para o usuário.
 */
final class WhatsAppLinkService
{
    private const CODE_TTL_MINUTES = 10;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly WhatsAppSessionRepository $sessionRepository,
    ) {}

    /** @return array{code: string, expires_at: Carbon} */
    public function generateLinkCode(User $user): array
    {
        $previousCode = Cache::pull("whatsapp.link_code_for_user.{$user->id}");

        if (is_string($previousCode)) {
            Cache::forget("whatsapp.link_code.{$previousCode}");
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(self::CODE_TTL_MINUTES);

        Cache::put("whatsapp.link_code.{$code}", $user->id, $expiresAt);
        Cache::put("whatsapp.link_code_for_user.{$user->id}", $code, $expiresAt);

        return ['code' => $code, 'expires_at' => $expiresAt];
    }

    /**
     * Resolve um código de vínculo para o usuário correspondente. Uso único
     * — `Cache::pull` remove o código atomicamente, então um código correto
     * nunca pode ser reaproveitado.
     */
    public function resolveLinkCode(string $code): ?User
    {
        $userId = Cache::pull("whatsapp.link_code.{$code}");

        if (! is_int($userId)) {
            return null;
        }

        return $this->userRepository->findById($userId);
    }

    public function link(User $user, string $phoneNumber): void
    {
        $this->userRepository->linkWhatsAppNumber($user, $phoneNumber);
    }

    public function unlink(User $user): void
    {
        $this->userRepository->unlinkWhatsAppNumber($user);
        $this->sessionRepository->resetForUser($user->id);
    }
}
