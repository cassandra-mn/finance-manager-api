<?php

namespace App\Services;

use App\Data\Categories\CreateCategoryData;
use App\Data\Categories\UpdateCategoryData;
use App\Enum\TransactionType;
use App\Exceptions\ServiceException;
use App\Http\Requests\Categories\ListCategoriesRequest;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\User;
use App\Repositories\CategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CategoryService
{
    /**
     * Categorias padrão criadas para um usuário recém-registrado, para que
     * ele não comece com a tela de categorias vazia.
     */
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

    public function __construct(
        private readonly CategoryRepository $repository,
    ) {}

    /** @return Collection<int, Category> */
    public function listForUser(ListCategoriesRequest $request): Collection
    {
        $type = $request->filled('type') ? TransactionType::from($request->string('type')->toString()) : null;

        return $this->repository->listForUser($request->user()->id, $type);
    }

    public function create(StoreCategoryRequest $request): Category
    {
        $data = CreateCategoryData::fromRequest($request, $request->user()->id);

        try {
            return $this->repository->create([
                'user_id' => $data->userId,
                'name' => $data->name,
                'type' => $data->type,
                'color' => $data->color,
                'icon' => $data->icon,
            ]);
        } catch (Throwable $e) {
            Log::error('finance.categories.create_failed', [
                'user_id' => $data->userId,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível criar a categoria.', previous: $e);
        }
    }

    public function update(Category $category, UpdateCategoryRequest $request): Category
    {
        $data = UpdateCategoryData::fromRequest($request);

        try {
            return $this->repository->update($category, array_filter([
                'name' => $data->name,
                'type' => $data->type,
                'color' => $data->color,
                'icon' => $data->icon,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (Throwable $e) {
            Log::error('finance.categories.update_failed', [
                'category_id' => $category->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível atualizar a categoria.', previous: $e);
        }
    }

    public function delete(Category $category): void
    {
        try {
            $this->repository->delete($category);
        } catch (Throwable $e) {
            Log::error('finance.categories.delete_failed', [
                'category_id' => $category->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível excluir a categoria.', previous: $e);
        }
    }

    public function seedDefaultsForUser(User $user): void
    {
        try {
            $this->repository->createMany(array_map(
                static fn (array $category): array => [
                    'user_id' => $user->id,
                    'name' => $category['name'],
                    'type' => $category['type'],
                ],
                self::DEFAULTS,
            ));
        } catch (Throwable $e) {
            Log::error('finance.categories.seed_defaults_failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            throw new ServiceException('Não foi possível criar as categorias padrão.', previous: $e);
        }
    }
}
