<?php

namespace App\Enum;

enum TransactionGroupType: string
{
    case CREDIT_CARD_INVOICE = 'credit_card_invoice';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_CARD_INVOICE => 'Fatura de Cartão de Crédito',
        };
    }
}
