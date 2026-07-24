<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\ListCategoriesRequest;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $service,
    ) {}

    public function index(ListCategoriesRequest $request): JsonResponse
    {
        $categories = $this->service->listForUser($request);

        return CategoryResource::collection($categories)->response();
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create($request);

        return (new CategoryResource($category))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Category $category): JsonResponse
    {
        return (new CategoryResource($category))->response();
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->service->update($category, $request);

        return (new CategoryResource($category))->response();
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->service->delete($category);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
