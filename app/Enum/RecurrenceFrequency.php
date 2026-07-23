<?php

namespace App\Enum;

enum RecurrenceFrequency: string
{
    case WEEKLY = 'weekly';
    case FORTNIGHTLY = 'fortnightly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Semanal',
            self::FORTNIGHTLY => 'Quinzenal',
            self::MONTHLY => 'Mensal',
            self::YEARLY => 'Anual',
        };
    }
}
