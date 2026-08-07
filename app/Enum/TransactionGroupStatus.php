<?php

namespace App\Enum;

enum TransactionGroupStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::CLOSED => 'Fechada',
            self::PARTIALLY_PAID => 'Parcialmente Paga',
            self::PAID => 'Paga',
        };
    }
}
