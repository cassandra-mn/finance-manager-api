<?php

namespace App\Enum;

enum TransactionGroupStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::CLOSED => 'Fechada',
            self::PAID => 'Paga',
        };
    }
}
