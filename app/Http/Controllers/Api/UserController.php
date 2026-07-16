<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\UserStoreRequest;
use App\Http\Requests\MasterData\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    use HandlesResourceQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->with(['department', 'roles']);

        // Filter berdasarkan role (slug) — join lewat relasi.
        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->query('role')));
        }

        $users = $this->paginateResource($query, $request, [
            'searchable' => ['name', 'email', 'phone'],
            'filters' => ['is_active' => 'is_active', 'department_id' => 'department_id'],
            'sortable' => ['name', 'email', 'is_active', 'created_at'],
            'default_sort' => ['name', 'asc'],
        ]);

        return UserResource::collection($users);
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],          // di-hash otomatis oleh cast
            'phone' => $data['phone'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        $user->roles()->sync($data['role_ids']);

        return (new UserResource($user->load(['department', 'roles'])))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['department', 'manager', 'roles']));
    }

    public function update(UserUpdateRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        $user->fill(collect($data)->only([
            'name', 'email', 'phone', 'department_id', 'manager_id', 'is_active',
        ])->toArray());

        if (! empty($data['password'])) {
            $user->password = $data['password'];      // di-hash otomatis oleh cast
        }

        $user->save();

        if (isset($data['role_ids'])) {
            $user->roles()->sync($data['role_ids']);
        }

        return new UserResource($user->load(['department', 'roles']));
    }

    public function destroy(Request $request, User $user): Response
    {
        abort_if($user->id === $request->user()->id, 422, 'Anda tidak dapat menghapus akun Anda sendiri.');

        $user->delete();

        return response()->noContent();
    }

    public function restore(int $id): UserResource
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return new UserResource($user->load(['department', 'roles']));
    }
}
