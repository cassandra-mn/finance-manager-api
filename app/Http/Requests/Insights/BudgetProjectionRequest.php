<?php

namespace App\Http\Requests\Insights;

use Illuminate\Foundation\Http\FormRequest;

class BudgetProjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_date' => ['sometimes', 'date'],
        ];
    }
}
