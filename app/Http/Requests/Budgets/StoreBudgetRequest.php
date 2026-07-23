<?php

namespace App\Http\Requests\Budgets;

use App\Enum\TransactionType;
use App\Models\Budget;
use App\Rules\CategoryTypeMatches;
use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                new ExistsForUser('categories'),
                new CategoryTypeMatches(fn () => TransactionType::EXPENSE),
                Rule::unique(Budget::class, 'category_id')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('reference_month', $this->integer('reference_month'))
                    ->where('reference_year', $this->integer('reference_year'))
                    ->whereNull('deleted_at')),
            ],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reference_month' => ['required', 'integer', 'between:1,12'],
            'reference_year' => ['required', 'integer', 'digits:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.unique' => 'Já existe um orçamento para esta categoria neste período.',
        ];
    }
}
