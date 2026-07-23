<?php

namespace App\Http\Requests\Recurrences;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListRecurrencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'integer'],
            'category_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', new Enum(TransactionType::class)],
            'frequency' => ['sometimes', new Enum(RecurrenceFrequency::class)],
            'is_active' => ['sometimes', 'in:true,false,0,1'],
            'search' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
