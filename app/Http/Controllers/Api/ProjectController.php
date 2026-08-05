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
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    use HandlesResourceQuery;

    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = $this->paginateResource(
            Project::query()->with(['manager:id,name', 'members:id,name'])->withCount('members'),
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
        $data = $request->validated();
        $memberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);

        $project = DB::transaction(function () use ($data, $memberIds) {
            $project = Project::create($data);
            $project->members()->sync($memberIds);

            return $project;
        });

        return (new ProjectResource($this->loaded($project)))->response()->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($this->loaded($project));
    }

    public function update(ProjectUpdateRequest $request, Project $project): ProjectResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $project, $data) {
            // member_ids absen = jangan sentuh penugasan yang sudah ada.
            if ($request->has('member_ids')) {
                $project->members()->sync($data['member_ids'] ?? []);
            }

            unset($data['member_ids']);
            $project->update($data);
        });

        return new ProjectResource($this->loaded($project));
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

    /** Relasi yang selalu ikut disajikan pada respons satuan. */
    private function loaded(Project $project): Project
    {
        return $project->load([
            'manager:id,name',
            'members:id,name,email,department_id',
            'members.department:id,name',
        ])->loadCount('members');
    }
}
