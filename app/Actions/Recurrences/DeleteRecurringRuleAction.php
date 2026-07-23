<?php

namespace App\Actions\Recurrences;

use App\Models\Recurrence;

final class DeleteRecurringRuleAction
{
    public function execute(Recurrence $recurrence): void
    {
        $recurrence->delete();
    }
}
