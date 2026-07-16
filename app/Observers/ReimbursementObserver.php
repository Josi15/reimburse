<?php

namespace App\Observers;

use App\Models\Reimbursement;

/**
 * Mengisi reimbursement_number otomatis: RMB-{tahun}-{urut 6 digit}.
 * withTrashed() dipakai agar nomor tak terpakai ulang oleh record soft-deleted.
 */
class ReimbursementObserver
{
    public function creating(Reimbursement $reimbursement): void
    {
        if (empty($reimbursement->reimbursement_number)) {
            $reimbursement->reimbursement_number = $this->nextNumber();
        }
    }

    private function nextNumber(): string
    {
        $prefix = 'RMB-'.date('Y').'-';

        $last = Reimbursement::withTrashed()
            ->where('reimbursement_number', 'like', $prefix.'%')
            ->orderByDesc('reimbursement_number')
            ->value('reimbursement_number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }
}
