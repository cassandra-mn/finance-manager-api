<?php

namespace App\Enum;

enum TransactionEntryType: string
{
    case FIXED = 'fixed';
    case VARIABLE = 'variable';
    case SINGLE = 'single';

    public function label(): string
    {
        return match ($this) {
            self::FIXED => 'Fixo',
            self::VARIABLE => 'Variável',
            self::SINGLE => 'Único',
        };
    }
}
