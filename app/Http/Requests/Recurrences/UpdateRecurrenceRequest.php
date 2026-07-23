<?php

namespace App\Http\Requests\Recurrences;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Models\Recurrence;
use App\Rules\CategoryTypeMatches;
use App\Rules\ExistsForUser;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'integer', new ExistsForUser('accounts')],
            'category_id' => ['sometimes', 'nullable', 'integer', new ExistsForUser('categories'), new CategoryTypeMatches(
                fn () => $this->filled('type')
                    ? TransactionType::tryFrom($this->string('type')->toString())
                    : $this->recurrence()?->type,
            )],
            'type' => ['sometimes', new Enum(TransactionType::class)],
            'entry_type' => ['sometimes', Rule::in([TransactionEntryType::FIXED->value, TransactionEntryType::VARIABLE->value])],
            'description' => ['sometimes', 'string', 'max:255'],
            'amount_cents' => ['sometimes', 'integer', 'min:1'],
            'frequency' => ['sometimes', new Enum(RecurrenceFrequency::class)],
            'start_date' => ['sometimes', 'date'],
            'next_due_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $recurrence = $this->recurrence();
            $startDate = $this->filled('start_date')
                ? Carbon::parse($this->string('start_date')->toString())
                : $recurrence?->start_date;

            if (! $startDate) {
                return;
            }

            if ($this->filled('next_due_date')) {
                $nextDueDate = Carbon::parse($this->string('next_due_date')->toString());

                if ($nextDueDate->lt($startDate)) {
                    $validator->errors()->add('next_due_date', 'A próxima data de vencimento não pode ser anterior à data inicial.');
                }
            }

            if ($this->filled('end_date')) {
                $endDate = Carbon::parse($this->string('end_date')->toString());

                if ($endDate->lt($startDate)) {
                    $validator->errors()->add('end_date', 'A data final não pode ser anterior à data inicial.');
                }
            }
        });
    }

    private function recurrence(): ?Recurrence
    {
        $recurrence = $this->route('recurrence');

        return $recurrence instanceof Recurrence ? $recurrence : null;
    }
}
