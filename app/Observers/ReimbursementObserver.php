<?php

namespace App\Observers;

use App\Models\Reimbursement;
use App\Support\SequentialNumber;

/**
 * Mengisi reimbursement_number otomatis: RMB-{tahun}-{urut 6 digit}.
 * withTrashed() dipakai agar nomor tak terpakai ulang oleh record soft-deleted.
 */
class ReimbursementObserver
{
    public function creating(Reimbursement $reimbursement): void
    {
        if (empty($reimbursement->reimbursement_number)) {
            $reimbursement->reimbursement_number = SequentialNumber::next(
                Reimbursement::class, 'reimbursement_number', 'RMB-'.date('Y').'-',
            );
        }
    }
}
