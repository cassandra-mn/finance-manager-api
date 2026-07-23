<?php

namespace App\Actions\Recurrences;

use App\Models\Recurrence;
use Illuminate\Validation\ValidationException;

final class ResumeRecurringRuleAction
{
    public function execute(Recurrence $recurrence): Recurrence
    {
        if ($recurrence->is_active) {
            throw ValidationException::withMessages([
                'is_active' => ['Esta recorrência já está ativa.'],
            ]);
        }

        $recurrence->update(['is_active' => true]);

        return $recurrence;
    }
}
