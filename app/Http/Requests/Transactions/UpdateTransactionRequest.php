<?php

namespace App\Http\Requests\Transactions;

use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'integer', new ExistsForUser('accounts')],
            'category_id' => ['sometimes', 'nullable', 'integer', new ExistsForUser('categories')],
            'type' => ['sometimes', new Enum(TransactionType::class)],
            'entry_type' => ['sometimes', new Enum(TransactionEntryType::class)],
            'description' => ['sometimes', 'string', 'max:255'],
            'amount_cents' => ['sometimes', 'integer', 'min:1'],
            'due_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
