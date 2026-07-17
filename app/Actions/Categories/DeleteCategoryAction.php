<?php

namespace App\Actions\Categories;

use App\Models\Category;

final class DeleteCategoryAction
{
    public function execute(Category $category): void
    {
        $category->delete();
    }
}
