<?php

namespace App\Enum;

enum StatementImportFormat: string
{
    case OFX = 'ofx';
    case CSV = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::OFX => 'OFX',
            self::CSV => 'CSV',
        };
    }
}
