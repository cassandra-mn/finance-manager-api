<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Categories\CreateCategoryAction;
use App\Actions\Categories\DeleteCategoryAction;
use App\Actions\Categories\UpdateCategoryAction;
use App\Data\Categories\CreateCategoryData;
use App\Data\Categories\UpdateCategoryData;
use App\Enum\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(Request $request, CategoryRepository $repository): JsonResponse
    {
        $type = $request->filled('type') ? TransactionType::from($request->string('type')->toString()) : null;

        $categories = $repository->listForUser($request->user()->id, $type);

        return CategoryResource::collection($categories)->response();
    }

    public function store(StoreCategoryRequest $request, CreateCategoryAction $action): JsonResponse
    {
        $category = $action->execute(CreateCategoryData::fromRequest($request, $request->user()->id));

        return (new CategoryResource($category))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Category $category): JsonResponse
    {
        return (new CategoryResource($category))->response();
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategoryAction $action): JsonResponse
    {
        $category = $action->execute($category, UpdateCategoryData::fromRequest($request));

        return (new CategoryResource($category))->response();
    }

    public function destroy(Category $category, DeleteCategoryAction $action): JsonResponse
    {
        $action->execute($category);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
