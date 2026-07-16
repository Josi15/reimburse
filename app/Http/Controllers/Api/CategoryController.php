<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\CategoryStoreRequest;
use App\Http\Requests\MasterData\CategoryUpdateRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    use HandlesResourceQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = $this->paginateResource(
            Category::query(),
            $request,
            [
                'searchable' => ['name', 'code', 'description'],
                'filters' => ['is_active' => 'is_active'],
                'sortable' => ['name', 'code', 'max_amount', 'is_active', 'created_at'],
                'default_sort' => ['name', 'asc'],
            ],
        );

        return CategoryResource::collection($categories);
    }

    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function update(CategoryUpdateRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Category $category): Response
    {
        $category->delete();

        return response()->noContent();
    }

    public function restore(int $id): CategoryResource
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $category->restore();

        return new CategoryResource($category);
    }
}
