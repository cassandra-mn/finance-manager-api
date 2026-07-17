<?php

namespace App\Actions\Categories;

use App\Data\Categories\CreateCategoryData;
use App\Models\Category;

final class CreateCategoryAction
{
    public function execute(CreateCategoryData $data): Category
    {
        return Category::create([
            'user_id' => $data->userId,
            'name' => $data->name,
            'type' => $data->type,
            'color' => $data->color,
            'icon' => $data->icon,
        ]);
    }
}
