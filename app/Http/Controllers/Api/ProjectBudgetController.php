<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectBudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pantauan sisa anggaran proyek (read-only). Project Manager memakai ini untuk
 * tahu berapa dana perusahaan yang masih tersisa di proyek yang dipegangnya.
 */
class ProjectBudgetController extends Controller
{
    public function __construct(private readonly ProjectBudgetService $budgets) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewBudgetAny', Project::class);

        return response()->json($this->budgets->summaryFor(
            $request->user(),
            $request->only('q', 'is_active'),
        ));
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('viewBudget', $project);

        return response()->json(['data' => $this->budgets->detail($project)]);
    }
}
