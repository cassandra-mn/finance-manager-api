<?php

namespace App\Enum;

enum TransactionOrigin: string
{
    case MANUAL = 'manual';
    case OPEN_FINANCE = 'open_finance';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::OPEN_FINANCE => 'Open Finance',
        };
    }
}
