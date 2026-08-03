<?php

namespace App\Enum;

/**
 * Status efetivo exibido ao usuário, combinando TransactionGroupStatus com a
 * data de vencimento. "Atrasada" não existe como coluna: é derivada, mesmo
 * princípio de TransactionDisplayStatus.
 */
enum TransactionGroupDisplayStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case OVERDUE = 'overdue';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Aberta',
            self::PENDING => 'Pendente',
            self::OVERDUE => 'Atrasada',
            self::PAID => 'Paga',
        };
    }
}
