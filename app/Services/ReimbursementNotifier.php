<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Models\Payment;
use App\Models\Reimbursement;
use App\Models\User;
use App\Notifications\ReimbursementActioned;
use App\Notifications\ReimbursementPaid;
use App\Notifications\ReimbursementSubmitted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Menentukan penerima dan mengirim notifikasi reimbursement (in-app + email).
 * Dipisah dari service domain agar logika "siapa dapat notifikasi apa"
 * terpusat di satu tempat.
 */
class ReimbursementNotifier
{
    /** Pengajuan diajukan → beri tahu approver tahap Manager. */
    public function submitted(Reimbursement $reimbursement): void
    {
        Notification::send(
            $this->managerApprovers($reimbursement),
            new ReimbursementSubmitted($reimbursement, 'manager'),
        );
    }

    /** Disetujui Manager → beri tahu approver tahap Finance. */
    public function forwardedToFinance(Reimbursement $reimbursement): void
    {
        Notification::send(
            $this->financeApprovers(),
            new ReimbursementSubmitted($reimbursement, 'finance'),
        );
    }

    /** Ada tindakan approval → beri tahu pemilik pengajuan. */
    public function actioned(Reimbursement $reimbursement, ApprovalLevel $level, ApprovalAction $action, ?string $notes): void
    {
        $owner = $reimbursement->user;
        $owner?->notify(new ReimbursementActioned($reimbursement, $level, $action, $notes));
    }

    /** Pembayaran berhasil → beri tahu pemilik pengajuan. */
    public function paid(Reimbursement $reimbursement, Payment $payment): void
    {
        $reimbursement->user?->notify(new ReimbursementPaid($reimbursement, $payment));
    }

    /** Atasan langsung pengaju bila aktif; jika tidak, semua Manager aktif. */
    private function managerApprovers(Reimbursement $reimbursement): Collection
    {
        $manager = $reimbursement->user?->manager;

        if ($manager && $manager->is_active) {
            return collect([$manager]);
        }

        return User::query()->active()->withRole('manager')->get();
    }

    private function financeApprovers(): Collection
    {
        return User::query()->active()->withRole('finance')->get();
    }
}
