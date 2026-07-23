<?php

namespace App\Policies;

use App\Enums\ReimbursementStatus;
use App\Models\Payment;
use App\Models\Reimbursement;
use App\Models\User;

/**
 * Otorisasi pembayaran. Finance memproses; Auditor/Admin dapat melihat.
 * Super Admin di-bypass oleh Gate::before.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payment.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        // Pemilik reimbursement boleh melihat pembayaran atas klaimnya sendiri.
        return $user->hasPermission('payment.view')
            || $payment->reimbursement?->user_id === $user->id;
    }

    /** Membuat pembayaran hanya untuk reimbursement Finance Approved. */
    public function create(User $user, ?Reimbursement $reimbursement = null): bool
    {
        if (! $user->hasPermission('payment.process')) {
            return false;
        }

        return $reimbursement === null
            || $reimbursement->status === ReimbursementStatus::FinanceApproved;
    }

    /**
     * Bukti pembayaran bersifat immutable (keputusan desain Phase 16):
     * tidak ada yang boleh mengubah/menghapusnya kecuali Super Admin, yang
     * sudah di-bypass lebih awal oleh Gate::before. Method eksplisit ini
     * mendokumentasikan intent tersebut agar tidak terbaca sebagai celah.
     */
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }
}
