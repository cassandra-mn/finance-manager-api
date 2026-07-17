<?php

namespace App\Http\Requests\Transactions;

use App\Enum\TransactionEntryType;
use App\Enum\TransactionType;
use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', new ExistsForUser('accounts')],
            'category_id' => ['nullable', 'integer', new ExistsForUser('categories')],
            'type' => ['required', new Enum(TransactionType::class)],
            'entry_type' => ['required', new Enum(TransactionEntryType::class)],
            'description' => ['required', 'string', 'max:255'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
