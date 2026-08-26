<?php

namespace App\Http\Requests\Goals;

use App\Rules\ExistsForUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', new ExistsForUser('accounts')],
            'name' => ['required', 'string', 'max:255'],
            'target_cents' => ['required', 'integer', 'min:1'],
            'target_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
