<?php

namespace App\Actions\Recurrences;

use App\Models\Recurrence;
use Illuminate\Validation\ValidationException;

final class PauseRecurringRuleAction
{
    public function execute(Recurrence $recurrence): Recurrence
    {
        if (! $recurrence->is_active) {
            throw ValidationException::withMessages([
                'is_active' => ['Esta recorrência já está pausada.'],
            ]);
        }

        $recurrence->update(['is_active' => false]);

        return $recurrence;
    }
}
