<?php

namespace App\Policies;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Enums\ReimbursementStatus;
use App\Models\Reimbursement;
use App\Models\User;

/**
 * Otorisasi reimbursement: gabungan permission (RBAC) + kepemilikan + status
 * (state machine). Super Admin di-bypass oleh Gate::before.
 *
 * Pemisahan tugas: setiap role boleh mengajukan reimbursement untuk dirinya
 * sendiri, termasuk Manager, Finance, dan Direksi. Karena itu approver DILARANG
 * memutuskan pengajuannya sendiri — klaim mereka harus dinilai approver lain
 * di tingkat yang sama. Satu orang juga tidak boleh meloloskan klaim yang sama
 * di lebih dari satu tingkat, sekalipun ia memegang beberapa permission
 * approval. Aturan ini berlaku untuk Super Admin juga (lihat Gate::before di
 * AppServiceProvider).
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
            || ($user->hasPermission('reimbursement.viewAny')
                && $this->inScope($user, $reimbursement));
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
        return $this->mayApproveAt(ApprovalLevel::Manager, $user, $reimbursement);
    }

    /** Persetujuan tingkat Finance (hanya saat level berlaku = Finance). */
    public function approveFinance(User $user, Reimbursement $reimbursement): bool
    {
        return $this->mayApproveAt(ApprovalLevel::Finance, $user, $reimbursement);
    }

    /** Persetujuan tingkat Direksi (pengajuan bernilai besar). */
    public function approveDirector(User $user, Reimbursement $reimbursement): bool
    {
        return $this->mayApproveAt(ApprovalLevel::Director, $user, $reimbursement);
    }

    /**
     * Syarat menyetujui pada suatu tingkat. Empat lapis, semuanya harus lolos:
     *
     * 1. Punya permission tingkat tersebut.
     * 2. Bukan pengajunya sendiri.
     * 3. Klaim itu berada dalam cakupan departemennya (Manager/Supervisor
     *    hanya menilai unitnya sendiri; Finance & Direksi lintas departemen).
     * 4. Tingkat itu memang yang sedang ditunggu (memperhitungkan ambang Direksi).
     * 5. Belum pernah memutuskan klaim ini di tingkat mana pun — mencegah satu
     *    orang meloloskan sebuah klaim melewati beberapa gerbang sekaligus
     *    hanya karena kebetulan memegang lebih dari satu permission approval.
     */
    private function mayApproveAt(ApprovalLevel $level, User $user, Reimbursement $reimbursement): bool
    {
        return $user->hasPermission($level->permission())
            && ! $this->owns($user, $reimbursement)
            && $this->inScope($user, $reimbursement)
            && $reimbursement->pendingApprovalLevel() === $level
            && ! $this->hasAlreadyDecided($user, $reimbursement);
    }

    /**
     * Klaim ini berada di cakupan departemen user? Padanan objek dari
     * Reimbursement::scopeVisibleTo() — keduanya harus sepakat, kalau tidak
     * daftar dan halaman detail bisa berbeda isi.
     */
    private function inScope(User $user, Reimbursement $reimbursement): bool
    {
        if ($user->seesAllDepartments()) {
            return true;
        }

        return $user->department_id !== null
            && $reimbursement->department_id === $user->department_id;
    }

    /**
     * Pernahkah user ini menyetujui/menolak klaim tersebut sebelumnya?
     *
     * Permintaan revisi tidak dihitung: itu mengembalikan klaim ke pengaju
     * untuk diperbaiki, bukan meloloskannya ke tahap berikutnya, jadi orang
     * yang sama tetap boleh menilai hasil perbaikannya.
     */
    private function hasAlreadyDecided(User $user, Reimbursement $reimbursement): bool
    {
        return $reimbursement->approvals()
            ->where('approver_id', $user->id)
            ->whereIn('action', [ApprovalAction::Approved->value, ApprovalAction::Rejected->value])
            ->exists();
    }

    private function owns(User $user, Reimbursement $reimbursement): bool
    {
        return $reimbursement->user_id === $user->id;
    }
}
