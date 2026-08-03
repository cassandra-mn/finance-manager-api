<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final readonly class CreditCardInvoiceCycle
{
    public function __construct(
        public Carbon $referenceMonth,
        public Carbon $closingDate,
        public Carbon $dueDate,
    ) {}
}
