<?php

namespace App\Enum;

enum WhatsAppSessionState: string
{
    case IDLE = 'idle';
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public function label(): string
    {
        return match ($this) {
            self::IDLE => 'Ocioso',
            self::AWAITING_CONFIRMATION => 'Aguardando confirmação',
        };
    }
}
