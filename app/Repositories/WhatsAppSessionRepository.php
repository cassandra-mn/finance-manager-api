<?php

namespace App\Repositories;

use App\Enum\WhatsAppSessionState;
use App\Models\WhatsAppSession;

final class WhatsAppSessionRepository
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    public function findByPhoneNumber(string $phoneNumber): ?WhatsAppSession
    {
        return WhatsAppSession::query()->where('phone_number', $phoneNumber)->first();
    }

    /**
     * Ao criar uma sessão nova para um número ainda sem sessão, resolve
     * `user_id` a partir de `User::whatsapp_number` — garante que uma sessão
     * recém-criada para um número já vinculado não seja tratada como
     * "não vinculada" só porque a sessão em si é nova.
     */
    public function firstOrCreateForPhoneNumber(string $phoneNumber): WhatsAppSession
    {
        $linkedUserId = $this->userRepository->findByWhatsAppNumber($phoneNumber)?->id;

        return WhatsAppSession::query()->firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['user_id' => $linkedUserId, 'state' => WhatsAppSessionState::IDLE],
        );
    }

    public function update(WhatsAppSession $session, array $attributes): WhatsAppSession
    {
        $session->fill($attributes);
        $session->save();

        return $session;
    }

    public function resetForUser(int $userId): void
    {
        WhatsAppSession::query()
            ->where('user_id', $userId)
            ->update(['state' => WhatsAppSessionState::IDLE->value, 'context' => null]);
    }
}
