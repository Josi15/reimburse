<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Otorisasi anggaran proyek. Project Manager hanya boleh melihat proyek yang
 * dipegangnya; pemegang `project.budget.viewAny` (Direksi/Admin/Finance/
 * Auditor) melihat semuanya. Super Admin di-bypass oleh Gate::before.
 */
class ProjectPolicy
{
    public function viewBudgetAny(User $user): bool
    {
        return $user->hasPermission('project.budget.view')
            || $user->hasPermission('project.budget.viewAny');
    }

    public function viewBudget(User $user, Project $project): bool
    {
        if ($user->hasPermission('project.budget.viewAny')) {
            return true;
        }

        return $user->hasPermission('project.budget.view')
            && $project->manager_id === $user->id;
    }
}
