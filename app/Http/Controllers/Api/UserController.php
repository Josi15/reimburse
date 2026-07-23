<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\UserStoreRequest;
use App\Http\Requests\MasterData\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    use HandlesResourceQuery;

    public function __construct(private readonly UserService $service) {}

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
        $user = $this->service->create($request->user(), $request->validated());

        return (new UserResource($user->load(['department', 'roles'])))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['department', 'manager', 'roles']));
    }

    public function update(UserUpdateRequest $request, User $user): UserResource
    {
        $user = $this->service->update($request->user(), $user, $request->validated());

        return new UserResource($user->load(['department', 'roles']));
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->service->delete($request->user(), $user);

        return response()->noContent();
    }

    public function restore(int $id): UserResource
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return new UserResource($user->load(['department', 'roles']));
    }
}
