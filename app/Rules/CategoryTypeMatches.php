<?php

namespace App\Rules;

use App\Enum\TransactionType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Garante que, quando uma categoria é informada, o tipo dela (income/expense)
 * seja compatível com o tipo do recurso que a referencia. O tipo esperado é
 * resolvido via closure para suportar tanto criação (tipo vem do payload)
 * quanto atualização parcial (tipo pode vir do registro existente).
 */
final class CategoryTypeMatches implements ValidationRule
{
    public function __construct(private readonly Closure $expectedType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $expectedType = ($this->expectedType)();

        if (! $expectedType instanceof TransactionType) {
            return;
        }

        $categoryType = DB::table('categories')->where('id', $value)->value('type');

        if ($categoryType !== null && $categoryType !== $expectedType->value) {
            $fail('O tipo da categoria não é compatível com o tipo informado.');
        }
    }
}
