<?php

namespace App\Actions\Categories;

use App\Data\Categories\UpdateCategoryData;
use App\Models\Category;

final class UpdateCategoryAction
{
    public function execute(Category $category, UpdateCategoryData $data): Category
    {
        $category->fill(array_filter([
            'name' => $data->name,
            'type' => $data->type,
            'color' => $data->color,
            'icon' => $data->icon,
        ], static fn (mixed $value): bool => $value !== null));

        $category->save();

        return $category;
    }
}
