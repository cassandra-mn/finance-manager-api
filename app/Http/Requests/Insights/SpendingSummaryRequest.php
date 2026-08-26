<?php

namespace App\Http\Requests\Insights;

use App\Enum\TransactionPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SpendingSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', new Enum(TransactionPeriod::class)],
            'reference_date' => ['sometimes', 'date'],
            'top_categories' => ['sometimes', 'integer', 'between:1,20'],
            'compare_to' => ['sometimes', 'in:previous_period,previous_year'],
        ];
    }
}
