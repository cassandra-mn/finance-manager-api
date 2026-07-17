<?php

namespace App\Http\Requests\Transactions;

use App\Constants\Pagination;
use App\Enum\TransactionDisplayStatus;
use App\Enum\TransactionEntryType;
use App\Enum\TransactionPeriod;
use App\Enum\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'integer'],
            'category_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', new Enum(TransactionType::class)],
            'entry_type' => ['sometimes', new Enum(TransactionEntryType::class)],
            'status' => ['sometimes', new Enum(TransactionDisplayStatus::class)],
            'period' => ['sometimes', new Enum(TransactionPeriod::class)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'search' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.Pagination::MAX_PER_PAGE],
        ];
    }
}
