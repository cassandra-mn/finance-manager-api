<?php

namespace App\Http\Requests\Categories;

use App\Enum\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', new Enum(TransactionType::class)],
        ];
    }
}
