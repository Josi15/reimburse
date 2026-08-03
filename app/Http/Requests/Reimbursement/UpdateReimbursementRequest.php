<?php

namespace App\Http\Requests\Reimbursement;

use App\Http\Requests\Reimbursement\Concerns\ChecksReimbursementLimits;
use App\Http\Requests\Reimbursement\Concerns\HandlesClaimTypeInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReimbursementRequest extends FormRequest
{
    use ChecksReimbursementLimits, HandlesClaimTypeInput;

    public function authorize(): bool
    {
        return true; // Otorisasi via policy (update) di controller.
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
            // Jenis pengajuan + field khusus jenisnya (fallback: jenis tersimpan).
            ...$this->claimTypeRules(required: false),
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer',
                Rule::exists('departments', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer',
                Rule::exists('projects', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'reason' => ['sometimes', 'required', 'string'],
            'amount' => ['sometimes', 'required', 'integer', 'min:1'],
            'expense_date' => ['nullable', 'date', 'before_or_equal:today'],
            'bank_account_id' => ['nullable', 'integer',
                Rule::exists('bank_accounts', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('is_active', true)],
            'attachments' => ['nullable', 'array', "max:{$maxFiles}"],
            'attachments.*' => ['file', "mimes:{$mimes}", "mimetypes:{$mimetypes}", "max:{$maxKb}"],
            'delete_attachment_ids' => ['nullable', 'array'],
            'delete_attachment_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->applyLimitChecks($v));
    }
}
