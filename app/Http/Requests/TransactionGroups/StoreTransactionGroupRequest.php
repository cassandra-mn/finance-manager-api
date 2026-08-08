<?php

namespace App\Http\Requests\TransactionGroups;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', new ExistsForUser('accounts')],
            'reference_month' => ['required', 'date_format:Y-m'],
        ];
    }
}
