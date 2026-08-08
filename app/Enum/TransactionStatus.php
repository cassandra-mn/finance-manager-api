<?php

namespace App\Enum;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PAID => 'Pago',
            self::PARTIALLY_PAID => 'Parcialmente Pago',
            self::CANCELLED => 'Cancelado',
        };
    }
}
