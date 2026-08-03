<?php

namespace App\Http\Requests\TransactionGroups;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class PayTransactionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_account_id' => ['required', 'integer', new ExistsForUser('accounts')],
            'paid_at' => ['sometimes', 'date'],
        ];
    }
}
