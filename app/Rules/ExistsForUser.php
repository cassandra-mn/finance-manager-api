<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Garante que um id referenciado em um payload (ex.: account_id, category_id)
 * pertence ao usuário autenticado. Evita que um usuário associe um lançamento
 * a uma conta/categoria de outra pessoa apenas informando o id no payload.
 */
final class ExistsForUser implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $userColumn = 'user_id',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $exists = DB::table($this->table)
            ->where('id', $value)
            ->where($this->userColumn, Auth::id())
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $fail('O campo :attribute é inválido.');
        }
    }
}
