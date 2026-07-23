<?php

namespace App\Policies;

use App\Enums\ApprovalLevel;
use App\Enums\ReimbursementStatus;
use App\Models\Reimbursement;
use App\Models\User;

/**
 * Otorisasi reimbursement: gabungan permission (RBAC) + kepemilikan + status
 * (state machine). Super Admin di-bypass oleh Gate::before.
 */
class ReimbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reimbursement.viewAny')
            || $user->hasPermission('reimbursement.view');
    }

    public function view(User $user, Reimbursement $reimbursement): bool
    {
        return $this->owns($user, $reimbursement)
            || $user->hasPermission('reimbursement.viewAny');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('reimbursement.create');
    }

    public function update(User $user, Reimbursement $reimbursement): bool
    {
        return $this->owns($user, $reimbursement)
            && $reimbursement->isEditable()
            && $user->hasPermission('reimbursement.update');
    }

    public function delete(User $user, Reimbursement $reimbursement): bool
    {
        return $this->owns($user, $reimbursement)
            && $reimbursement->status === ReimbursementStatus::Draft
            && $user->hasPermission('reimbursement.delete');
    }

    public function submit(User $user, Reimbursement $reimbursement): bool
    {
        return $this->owns($user, $reimbursement)
            && $reimbursement->isEditable()
            && $user->hasPermission('reimbursement.submit');
    }

    /** Persetujuan tingkat Manager (hanya saat level berlaku = Manager). */
    public function approveManager(User $user, Reimbursement $reimbursement): bool
    {
        return $user->hasPermission('reimbursement.approve.manager')
            && $reimbursement->status->approvalLevel() === ApprovalLevel::Manager;
    }

    /** Persetujuan tingkat Finance (hanya saat level berlaku = Finance). */
    public function approveFinance(User $user, Reimbursement $reimbursement): bool
    {
        return $user->hasPermission('reimbursement.approve.finance')
            && $reimbursement->status->approvalLevel() === ApprovalLevel::Finance;
    }

    private function owns(User $user, Reimbursement $reimbursement): bool
    {
        return $reimbursement->user_id === $user->id;
    }
}
