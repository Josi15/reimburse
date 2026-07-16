<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\RoleStoreRequest;
use App\Http\Requests\MasterData\RoleUpdateRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    use HandlesResourceQuery;

    /** Role inti sistem yang tidak boleh dihapus. */
    private const CORE_ROLES = ['super_admin', 'admin', 'employee', 'manager', 'finance', 'auditor'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = $this->paginateResource(
            Role::query()->withCount('users')->with('permissions'),
            $request,
            [
                'searchable' => ['name', 'display_name', 'description'],
                'sortable' => ['name', 'display_name', 'created_at'],
                'default_sort' => ['name', 'asc'],
            ],
        );

        return RoleResource::collection($roles);
    }

    public function store(RoleStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'],
            'description' => $data['description'] ?? null,
            'guard_name' => 'web',
        ]);

        $role->permissions()->sync($data['permission_ids'] ?? []);

        return (new RoleResource($role->load('permissions')))->response()->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function update(RoleUpdateRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();

        $role->update(collect($data)->only(['name', 'display_name', 'description'])->toArray());

        if (isset($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function destroy(Role $role): Response
    {
        abort_if(in_array($role->name, self::CORE_ROLES, true), 422, 'Role inti sistem tidak dapat dihapus.');
        abort_if($role->users()->exists(), 422, 'Role masih digunakan oleh user dan tidak dapat dihapus.');

        $role->permissions()->detach();
        $role->delete();

        return response()->noContent();
    }
}
