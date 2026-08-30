<?php

namespace App\Http\Requests\Insights;

use Illuminate\Foundation\Http\FormRequest;

class DebtPayoffPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer'],
            'monthly_payment_cents' => ['required', 'integer', 'min:1'],
            'annual_interest_rate_percentage' => ['sometimes', 'numeric', 'min:0', 'max:999'],
        ];
    }
}
