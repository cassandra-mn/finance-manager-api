<?php

namespace App\Actions\Categories;

use App\Enum\TransactionType;
use App\Models\Category;
use App\Models\User;

/**
 * Cria categorias padrão para um usuário recém-registrado, para que ele não
 * comece com a tela de categorias vazia.
 */
final class SeedDefaultCategoriesForUserAction
{
    private const DEFAULTS = [
        ['name' => 'Salário', 'type' => TransactionType::INCOME],
        ['name' => 'Freelance', 'type' => TransactionType::INCOME],
        ['name' => 'Outras receitas', 'type' => TransactionType::INCOME],
        ['name' => 'Moradia', 'type' => TransactionType::EXPENSE],
        ['name' => 'Alimentação', 'type' => TransactionType::EXPENSE],
        ['name' => 'Transporte', 'type' => TransactionType::EXPENSE],
        ['name' => 'Saúde', 'type' => TransactionType::EXPENSE],
        ['name' => 'Educação', 'type' => TransactionType::EXPENSE],
        ['name' => 'Lazer', 'type' => TransactionType::EXPENSE],
        ['name' => 'Outras despesas', 'type' => TransactionType::EXPENSE],
    ];

    public function execute(User $user): void
    {
        foreach (self::DEFAULTS as $category) {
            Category::create([
                'user_id' => $user->id,
                'name' => $category['name'],
                'type' => $category['type'],
            ]);
        }
    }
}
