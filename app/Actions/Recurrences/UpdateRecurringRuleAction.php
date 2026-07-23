<?php

namespace App\Actions\Recurrences;

use App\Data\Recurrences\UpdateRecurrenceData;
use App\Models\Recurrence;
use Illuminate\Support\Facades\DB;

final class UpdateRecurringRuleAction
{
    public function execute(Recurrence $recurrence, UpdateRecurrenceData $data): Recurrence
    {
        return DB::transaction(function () use ($recurrence, $data): Recurrence {
            $recurrence->fill(array_filter([
                'account_id' => $data->accountId,
                'category_id' => $data->categoryId,
                'type' => $data->type,
                'entry_type' => $data->entryType,
                'description' => $data->description,
                'amount_cents' => $data->amountCents,
                'frequency' => $data->frequency,
                'start_date' => $data->startDate,
                'next_due_date' => $data->nextDueDate,
                'end_date' => $data->endDate,
                'notes' => $data->notes,
            ], static fn (mixed $value): bool => $value !== null));

            $recurrence->save();

            return $recurrence;
        });
    }
}
