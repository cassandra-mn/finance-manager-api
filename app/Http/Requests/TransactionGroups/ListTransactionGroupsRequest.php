<?php

namespace App\Http\Requests\TransactionGroups;

use App\Enum\TransactionGroupStatus;
use App\Enum\TransactionGroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListTransactionGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'integer'],
            'type' => ['sometimes', new Enum(TransactionGroupType::class)],
            'status' => ['sometimes', new Enum(TransactionGroupStatus::class)],
        ];
    }
}
