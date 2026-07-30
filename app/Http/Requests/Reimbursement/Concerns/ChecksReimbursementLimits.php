<?php

namespace App\Http\Requests\Reimbursement\Concerns;

use App\Models\Category;
use App\Models\Reimbursement;
use Illuminate\Validation\Validator;

/**
 * Validasi plafon nominal reimbursement:
 *  - KATEGORI: batas per pengajuan (max_amount).
 *  - JABATAN/role: batas BULANAN — total pengajuan user pada bulan berjalan
 *    (tidak termasuk yang ditolak) + nominal ini tidak boleh melebihi plafon.
 * Dipakai bersama oleh Store & Update request agar aturannya satu sumber.
 */
trait ChecksReimbursementLimits
{
    protected function applyLimitChecks(Validator $validator): void
    {
        // Butuh category_id & amount; pada update keduanya bisa tak dikirim.
        if (! $this->filled('category_id') || ! $this->filled('amount')) {
            return;
        }

        $amount = (int) $this->input('amount');

        // Plafon kategori (per pengajuan).
        $category = Category::find($this->input('category_id'));
        if ($category && $category->max_amount !== null && $amount > $category->max_amount) {
            $validator->errors()->add('amount',
                'Nominal melebihi plafon kategori (Rp '.number_format($category->max_amount, 0, ',', '.').').');
        }

        // Plafon jabatan (BULANAN). null = tanpa batas.
        $limit = $this->user()->reimbursementLimit();
        if ($limit !== null) {
            $used = $this->user()->monthlyReimbursementUsed($this->currentReimbursementId());

            if ($used + $amount > $limit) {
                $sisa = max(0, $limit - $used);
                $validator->errors()->add('amount',
                    'Melebihi plafon bulanan jabatan Anda (Rp '.number_format($limit, 0, ',', '.').'). '.
                    'Terpakai bulan ini Rp '.number_format($used, 0, ',', '.').
                    ', sisa Rp '.number_format($sisa, 0, ',', '.').'.');
            }
        }
    }

    /** ID reimbursement yang sedang diedit (agar tak dihitung ganda); null saat create. */
    private function currentReimbursementId(): ?int
    {
        $current = $this->route('reimbursement');

        return $current instanceof Reimbursement ? $current->getKey() : ($current ? (int) $current : null);
    }
}
