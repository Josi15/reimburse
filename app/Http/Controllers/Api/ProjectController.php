<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\HandlesResourceQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ProjectStoreRequest;
use App\Http\Requests\MasterData\ProjectUpdateRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    use HandlesResourceQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $this->paginateResource(
            Project::query()->with('manager:id,name'),
            $request,
            [
                'searchable' => ['code', 'name', 'description'],
                'filters' => ['is_active' => 'is_active', 'manager_id' => 'manager_id'],
                'sortable' => ['code', 'name', 'budget', 'start_date', 'end_date', 'is_active', 'created_at'],
                'default_sort' => ['created_at', 'desc'],
            ],
        );

        return ProjectResource::collection($projects);
    }

    public function store(ProjectStoreRequest $request): JsonResponse
    {
        $project = Project::create($request->validated());

        return (new ProjectResource($project->load('manager:id,name')))->response()->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load('manager:id,name'));
    }

    public function update(ProjectUpdateRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());

        return new ProjectResource($project->load('manager:id,name'));
    }

    public function destroy(Project $project): Response
    {
        $project->delete();

        return response()->noContent();
    }

    public function restore(int $id): ProjectResource
    {
        $project = Project::onlyTrashed()->findOrFail($id);
        $project->restore();

        return new ProjectResource($project);
    }
}
