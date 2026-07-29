<?php

namespace App\Http\Requests\Reimbursement\Concerns;

use App\Models\Category;
use Illuminate\Validation\Validator;

/**
 * Validasi plafon nominal reimbursement: yang paling ketat antara plafon
 * KATEGORI (max_amount) dan plafon JABATAN/role (reimbursementLimit()).
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

        // Plafon kategori.
        $category = Category::find($this->input('category_id'));
        if ($category && $category->max_amount !== null && $amount > $category->max_amount) {
            $validator->errors()->add('amount', $this->ceilingMessage('kategori', $category->max_amount));
        }

        // Plafon jabatan (role). null = tanpa batas.
        $limit = $this->user()->reimbursementLimit();
        if ($limit !== null && $amount > $limit) {
            $validator->errors()->add('amount', $this->ceilingMessage('jabatan Anda', $limit));
        }
    }

    private function ceilingMessage(string $scope, int $max): string
    {
        return "Nominal melebihi plafon {$scope} (Rp ".number_format($max, 0, ',', '.').').';
    }
}
