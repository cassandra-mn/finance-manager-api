<?php

namespace App\Enum;

/**
 * Status efetivo exibido ao usuário, combinando TransactionStatus com a data de
 * vencimento. "Atrasado" não existe como coluna no banco: é derivado para evitar
 * um job diário que precisaria "virar" o status de cada transação vencida.
 */
enum TransactionDisplayStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendente',
            self::PAID => 'Pago',
            self::OVERDUE => 'Atrasado',
            self::CANCELLED => 'Cancelado',
        };
    }
}
