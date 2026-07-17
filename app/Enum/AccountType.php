<?php

namespace App\Enum;

enum AccountType: string
{
    case CHECKING = 'checking';
    case SAVINGS = 'savings';
    case WALLET = 'wallet';
    case CREDIT_CARD = 'credit_card';
    case INVESTMENT = 'investment';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CHECKING => 'Conta corrente',
            self::SAVINGS => 'Poupança',
            self::WALLET => 'Carteira',
            self::CREDIT_CARD => 'Cartão de crédito',
            self::INVESTMENT => 'Investimento',
            self::OTHER => 'Outro',
        };
    }
}
