<?php

namespace App\Actions\Recurrences;

use App\Data\Recurrences\CreateRecurrenceData;
use App\Models\Recurrence;
use Illuminate\Support\Facades\DB;

final class CreateRecurringRuleAction
{
    public function execute(CreateRecurrenceData $data): Recurrence
    {
        return DB::transaction(fn (): Recurrence => Recurrence::create([
            'user_id' => $data->userId,
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
            'is_active' => true,
        ]));
    }
}
