<?php

namespace App\Enum;

enum RecurrenceFrequency: string
{
    case WEEKLY = 'weekly';
    case BIWEEKLY = 'biweekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Semanal',
            self::BIWEEKLY => 'Quinzenal',
            self::MONTHLY => 'Mensal',
            self::YEARLY => 'Anual',
        };
    }
}
