<?php

namespace App\Enum;

enum TransactionPeriod: string
{
    case WEEK = 'week';
    case FORTNIGHT = 'fortnight';
    case MONTH = 'month';
    case QUARTER = 'quarter';
    case YEAR = 'year';

    public function label(): string
    {
        return match ($this) {
            self::WEEK => 'Semanal',
            self::FORTNIGHT => 'Quinzenal',
            self::MONTH => 'Mensal',
            self::QUARTER => 'Trimestral',
            self::YEAR => 'Anual',
        };
    }
}
