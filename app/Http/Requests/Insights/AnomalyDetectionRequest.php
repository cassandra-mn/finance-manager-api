<?php

namespace App\Http\Requests\Insights;

use App\Enum\TransactionPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AnomalyDetectionRequest extends FormRequest
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
            'lookback_periods' => ['sometimes', 'integer', 'between:1,12'],
            'threshold_percentage' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
