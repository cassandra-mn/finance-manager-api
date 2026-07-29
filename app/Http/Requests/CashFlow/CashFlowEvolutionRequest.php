<?php

namespace App\Http\Requests\CashFlow;

use Illuminate\Foundation\Http\FormRequest;

class CashFlowEvolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_date' => ['sometimes', 'date'],
            'months' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ];
    }
}
