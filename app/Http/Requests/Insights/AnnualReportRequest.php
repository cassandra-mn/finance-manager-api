<?php

namespace App\Http\Requests\Insights;

use Illuminate\Foundation\Http\FormRequest;

class AnnualReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'top_categories' => ['sometimes', 'integer', 'between:1,20'],
        ];
    }
}
