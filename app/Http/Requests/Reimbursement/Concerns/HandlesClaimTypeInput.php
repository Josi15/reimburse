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

    /**
     * Aturan untuk kolom claim_type + seluruh field detail jenis tersebut.
     *
     * Daftar nilai yang sah dibatasi pada jenis yang boleh dipakai user ini
     * (Employee: biaya & lembur saja). Ini penjagaan sesungguhnya — menyaring
     * pilihan di form hanya soal tampilan dan bisa dilewati lewat API.
     */
    protected function claimTypeRules(bool $required = true): array
    {
        $allowed = ClaimType::casesFor($this->user());
        $values = implode(',', array_column($allowed, 'value'));

        return [
            'claim_type' => [$required ? 'required' : 'sometimes', 'string', "in:{$values}"],
            'details' => ['nullable', 'array'],
            ...$this->claimType()->validationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'claim_type.in' => 'Anda tidak berhak mengajukan jenis ini. '
                .'Jenis yang tersedia untuk Anda: '
                .implode(', ', array_map(
                    fn (ClaimType $t) => $t->label(),
                    ClaimType::casesFor($this->user()),
                )).'.',
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
