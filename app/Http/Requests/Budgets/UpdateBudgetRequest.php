<?php

namespace App\Http\Requests\Budgets;

use App\Enum\TransactionType;
use App\Models\Budget;
use App\Rules\CategoryTypeMatches;
use App\Rules\ExistsForUser;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', new ExistsForUser('categories'), new CategoryTypeMatches(
                fn () => TransactionType::EXPENSE,
            )],
            'amount_cents' => ['sometimes', 'integer', 'min:1'],
            'reference_month' => ['sometimes', 'integer', 'between:1,12'],
            'reference_year' => ['sometimes', 'integer', 'digits:4'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $budget = $this->budget();

            if (! $budget) {
                return;
            }

            $categoryId = $this->filled('category_id') ? (int) $this->integer('category_id') : $budget->category_id;
            $referenceMonth = $this->filled('reference_month') ? (int) $this->integer('reference_month') : $budget->reference_month;
            $referenceYear = $this->filled('reference_year') ? (int) $this->integer('reference_year') : $budget->reference_year;

            $duplicateExists = Budget::query()
                ->where('user_id', $this->user()->id)
                ->where('category_id', $categoryId)
                ->where('reference_month', $referenceMonth)
                ->where('reference_year', $referenceYear)
                ->whereKeyNot($budget->id)
                ->exists();

            if ($duplicateExists) {
                $validator->errors()->add('category_id', 'Já existe um orçamento para esta categoria neste período.');
            }
        });
    }

    private function budget(): ?Budget
    {
        $budget = $this->route('budget');

        return $budget instanceof Budget ? $budget : null;
    }
}
