<?php

namespace App\Http\Requests\Recurrences;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Rules\CategoryTypeMatches;
use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', new ExistsForUser('accounts')],
            'category_id' => ['nullable', 'integer', new ExistsForUser('categories'), new CategoryTypeMatches(
                fn () => $this->filled('type') ? TransactionType::tryFrom($this->string('type')->toString()) : null,
            )],
            'type' => ['required', new Enum(TransactionType::class)],
            'entry_type' => ['required', Rule::in([TransactionEntryType::FIXED->value, TransactionEntryType::VARIABLE->value])],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'frequency' => ['required', new Enum(RecurrenceFrequency::class)],
            'start_date' => ['required', 'date'],
            'next_due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
