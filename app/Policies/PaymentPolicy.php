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
        return $user->hasPermission('payment.view');
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
}
