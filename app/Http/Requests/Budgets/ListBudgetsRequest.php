<?php

namespace App\Http\Requests\Budgets;

use Illuminate\Foundation\Http\FormRequest;

class ListBudgetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer'],
            'reference_month' => ['sometimes', 'integer', 'between:1,12'],
            'reference_year' => ['sometimes', 'integer', 'digits:4'],
            'reference_date' => ['sometimes', 'date'],
        ];
    }
}
