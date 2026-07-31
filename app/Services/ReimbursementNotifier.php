<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalLevel;
use App\Enums\ReimbursementStatus;
use App\Models\Payment;
use App\Models\Reimbursement;
use App\Models\User;
use App\Notifications\ReimbursementActioned;
use App\Notifications\ReimbursementPaid;
use App\Notifications\ReimbursementReadyForPayment;
use App\Notifications\ReimbursementSubmitted;
use App\Notifications\ReimbursementSubmittedReceipt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Menentukan penerima dan mengirim notifikasi reimbursement (in-app + email).
 * Dipisah dari service domain agar logika "siapa dapat notifikasi apa"
 * terpusat di satu tempat.
 *
 * Rantai lengkap satu siklus pengajuan:
 *   1. submit           → approver Manager diberi tahu, PENGAJU dapat tanda terima
 *   2. Manager approve  → pengaju dikabari + approver Finance diberi tahu
 *   3. Finance approve  → pengaju dikabari + petugas pembayaran diberi tahu
 *   4. dibayar          → PENGAJU dikabari dana cair (lingkaran tertutup)
 * Penolakan/permintaan revisi di tahap mana pun juga kembali ke pengaju.
 */
class ReimbursementNotifier
{
    /** Pengajuan diajukan → beri tahu approver Manager + tanda terima ke pengaju. */
    public function submitted(Reimbursement $reimbursement): void
    {
        $approvers = $this->managerApprovers($reimbursement);

        Notification::send($approvers, new ReimbursementSubmitted($reimbursement, 'manager'));

        // Titik awal rantai: pengaju tahu berkasnya masuk & siapa yang menilai.
        $reimbursement->user?->notify(
            new ReimbursementSubmittedReceipt($reimbursement, $approvers->count() === 1 ? $approvers->first()?->name : null),
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

    /** Disetujui Finance → beri tahu petugas yang berhak memproses pembayaran. */
    public function readyForPayment(Reimbursement $reimbursement): void
    {
        Notification::send(
            $this->paymentProcessors(),
            new ReimbursementReadyForPayment($reimbursement),
        );
    }

    /** Ada tindakan approval → beri tahu pemilik pengajuan. */
    public function actioned(Reimbursement $reimbursement, ApprovalLevel $level, ApprovalAction $action, ?string $notes): void
    {
        $owner = $reimbursement->user;
        $owner?->notify(new ReimbursementActioned($reimbursement, $level, $action, $notes));
    }

    /** Pembayaran berhasil → beri tahu pemilik pengajuan (rantai tertutup). */
    public function paid(Reimbursement $reimbursement, Payment $payment): void
    {
        $reimbursement->user?->notify(new ReimbursementPaid($reimbursement, $payment));
    }

    /**
     * Notifikasi lanjutan sesuai status baru setelah sebuah approval disetujui.
     * Dipanggil ApprovalService agar pemetaan status → penerima berikutnya
     * tinggal di satu tempat.
     */
    public function advancedTo(Reimbursement $reimbursement, ReimbursementStatus $status): void
    {
        match ($status) {
            ReimbursementStatus::ManagerApproved => $this->forwardedToFinance($reimbursement),
            ReimbursementStatus::FinanceApproved => $this->readyForPayment($reimbursement),
            default => null,
        };
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

    /**
     * Semua user aktif yang boleh memproses pembayaran (permission
     * payment.process), bukan hanya role finance — agar admin/super admin
     * yang bertugas mencairkan dana juga ikut dikabari.
     */
    private function paymentProcessors(): Collection
    {
        return User::query()->active()
            ->whereHas('roles.permissions', fn ($q) => $q->where('permissions.name', 'payment.process'))
            ->get();
    }
}
