<?php

namespace App\Http\Requests\Reimbursement;

use App\Http\Requests\Reimbursement\Concerns\ChecksReimbursementLimits;
use App\Http\Requests\Reimbursement\Concerns\HandlesClaimTypeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReimbursementRequest extends FormRequest
{
    use ChecksReimbursementLimits, HandlesClaimTypeInput;

    public function authorize(): bool
    {
        return true; // Otorisasi via policy (create) di controller.
    }

    /** Nominal jenis terhitung (barang/layanan/lembur) diturunkan di server. */
    protected function prepareForValidation(): void
    {
        // Urutannya penting: tarif dari jabatan dipasang dulu, baru
        // nominalnya dihitung dari detail yang sudah bersih.
        $this->applyServerSourcedDetails();
        $this->computeAmountFromDetails();
    }

    public function rules(): array
    {
        $maxKb = config('reimbursement.max_file_size_kb');
        $mimes = implode(',', config('reimbursement.allowed_mimes'));
        $mimetypes = implode(',', config('reimbursement.allowed_mimetypes'));
        $maxFiles = config('reimbursement.max_files_per_request');

        return [
            // Jenis pengajuan + field khusus jenisnya (default: expense).
            ...$this->claimTypeRules(required: false),
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            // Departemen yang menanggung biaya. Kosong = ikut departemen pengaju.
            'department_id' => ['nullable', 'integer',
                Rule::exists('departments', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer',
                Rule::exists('projects', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'reason' => ['required', 'string'],                 // alasan wajib
            'amount' => ['required', 'integer', 'min:1'],
            'expense_date' => ['nullable', 'date', 'before_or_equal:today'],
            // Rekening opsional di draft; kepemilikan & keaktifan divalidasi bila diisi.
            'bank_account_id' => ['nullable', 'integer',
                Rule::exists('bank_accounts', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('is_active', true)],
            // Bukti opsional (pengajuan tanpa bukti diizinkan).
            'attachments' => ['nullable', 'array', "max:{$maxFiles}"],
            'attachments.*' => ['file', "mimes:{$mimes}", "mimetypes:{$mimetypes}", "max:{$maxKb}"],
        ];
    }

    /** Nominal dibatasi plafon kategori & jabatan (yang paling ketat). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->applyLimitChecks($v));
    }
}
