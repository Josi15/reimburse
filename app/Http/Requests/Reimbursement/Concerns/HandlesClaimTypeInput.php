<?php

namespace App\Http\Requests\Reimbursement\Concerns;

use App\Enums\ClaimType;
use App\Models\Reimbursement;

/**
 * Dukungan input jenis pengajuan (barang / layanan / lembur / biaya).
 *
 * - Aturan validasi `details.*` dibangun dari definisi field di ClaimType,
 *   jadi menambah field cukup di enum tanpa menyentuh request ini.
 * - Untuk jenis dengan nominal terhitung (qty × harga, jam × upah), nilai
 *   `amount` DIHITUNG ULANG di server sebelum validasi. Angka dari client
 *   tidak dipercaya, sekaligus membuat cek plafon tetap akurat.
 */
trait HandlesClaimTypeInput
{
    /** Jenis pengajuan efektif: dari input, atau dari data tersimpan saat edit. */
    protected function claimType(): ClaimType
    {
        $fromInput = ClaimType::tryFrom((string) $this->input('claim_type'));

        if ($fromInput) {
            return $fromInput;
        }

        $current = $this->route('reimbursement');

        return $current instanceof Reimbursement
            ? ($current->claim_type ?? ClaimType::Expense)
            : ClaimType::Expense;
    }

    /** Aturan untuk kolom claim_type + seluruh field detail jenis tersebut. */
    protected function claimTypeRules(bool $required = true): array
    {
        $values = implode(',', array_column(ClaimType::cases(), 'value'));

        return [
            'claim_type' => [$required ? 'required' : 'sometimes', 'string', "in:{$values}"],
            'details' => ['nullable', 'array'],
            ...$this->claimType()->validationRules(),
        ];
    }

    /** Isi ulang `amount` untuk jenis yang nominalnya dihitung sistem. */
    protected function computeAmountFromDetails(): void
    {
        $type = $this->claimType();

        if (! $type->hasComputedAmount()) {
            return;
        }

        $amount = $type->computeAmount((array) $this->input('details', []));

        if ($amount !== null) {
            $this->merge(['amount' => $amount]);
        }
    }
}
