<?php

namespace App\Http\Requests\Insights;

use Illuminate\Foundation\Http\FormRequest;

class CashFlowForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_date' => ['sometimes', 'date'],
            'months' => ['sometimes', 'integer', 'between:1,12'],
        ];
    }
}
