<?php

namespace App\Enum;

enum TransactionOrigin: string
{
    case MANUAL = 'manual';
    case STATEMENT_IMPORT = 'statement_import';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::STATEMENT_IMPORT => 'Importação de extrato',
        };
    }
}
