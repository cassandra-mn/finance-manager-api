<?php

namespace App\Enum;

enum BudgetStatus: string
{
    case SAFE = 'safe';
    case WARNING = 'warning';
    case EXCEEDED = 'exceeded';

    public function label(): string
    {
        return match ($this) {
            self::SAFE => 'Dentro do limite',
            self::WARNING => 'Atenção',
            self::EXCEEDED => 'Excedido',
        };
    }
}
