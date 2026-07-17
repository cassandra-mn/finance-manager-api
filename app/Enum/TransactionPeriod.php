<?php

namespace App\Enum;

enum TransactionPeriod: string
{
    case WEEK = 'week';
    case FORTNIGHT = 'fortnight';
    case MONTH = 'month';
}
