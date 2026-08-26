<?php

namespace App\Http\Requests\Goals;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['sometimes', 'integer', new ExistsForUser('accounts')],
            'name' => ['sometimes', 'string', 'max:255'],
            'target_cents' => ['sometimes', 'integer', 'min:1'],
            'target_date' => ['nullable', 'date'],
            'color' => ['sometimes', 'string', 'max:20'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
