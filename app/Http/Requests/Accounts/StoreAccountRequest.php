<?php

namespace App\Http\Requests\Accounts;

use App\Enum\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(AccountType::class)],
            'initial_balance_cents' => ['required', 'integer'],
            'credit_limit_cents' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
