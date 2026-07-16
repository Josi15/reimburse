<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\DepartmentStoreRequest;
use App\Http\Requests\MasterData\DepartmentUpdateRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DepartmentController extends Controller
{
    use HandlesResourceQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $departments = $this->paginateResource(
            Department::query()->withCount('users'),
            $request,
            [
                'searchable' => ['name', 'code', 'description'],
                'filters' => ['is_active' => 'is_active'],
                'sortable' => ['name', 'code', 'is_active', 'created_at'],
                'default_sort' => ['name', 'asc'],
            ],
        );

        return DepartmentResource::collection($departments);
    }

    public function store(DepartmentStoreRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    public function show(Department $department): DepartmentResource
    {
        return new DepartmentResource($department->loadCount('users'));
    }

    public function update(DepartmentUpdateRequest $request, Department $department): DepartmentResource
    {
        $department->update($request->validated());

        return new DepartmentResource($department->loadCount('users'));
    }

    public function destroy(Department $department): Response
    {
        $department->delete();

        return response()->noContent();
    }

    public function restore(int $id): DepartmentResource
    {
        $department = Department::onlyTrashed()->findOrFail($id);
        $department->restore();

        return new DepartmentResource($department);
    }
}
