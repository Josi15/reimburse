<?php

namespace App\Policies;

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

    /** Persetujuan tingkat Manager (hanya dari status Submitted). */
    public function approveManager(User $user, Reimbursement $reimbursement): bool
    {
        return $user->hasPermission('reimbursement.approve.manager')
            && $reimbursement->status === ReimbursementStatus::Submitted;
    }

    /** Persetujuan tingkat Finance (hanya dari status Manager Approved). */
    public function approveFinance(User $user, Reimbursement $reimbursement): bool
    {
        return $user->hasPermission('reimbursement.approve.finance')
            && $reimbursement->status === ReimbursementStatus::ManagerApproved;
    }

    private function owns(User $user, Reimbursement $reimbursement): bool
    {
        return $reimbursement->user_id === $user->id;
    }
}
