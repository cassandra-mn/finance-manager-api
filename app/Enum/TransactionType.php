<?php

namespace App\Enum;

enum TransactionType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Receita',
            self::EXPENSE => 'Despesa',
        };
    }
}
