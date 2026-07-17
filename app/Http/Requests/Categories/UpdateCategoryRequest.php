<?php

namespace App\Http\Requests\Categories;

use App\Enum\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', new Enum(TransactionType::class)],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
