<?php

namespace App\Http\Requests\Reimbursement;

use App\Http\Requests\Reimbursement\Concerns\ChecksReimbursementLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReimbursementRequest extends FormRequest
{
    use ChecksReimbursementLimits;

    public function authorize(): bool
    {
        return true; // Otorisasi via policy (update) di controller.
    }

    public function rules(): array
    {
        $maxKb = config('reimbursement.max_file_size_kb');
        $mimes = implode(',', config('reimbursement.allowed_mimes'));
        $mimetypes = implode(',', config('reimbursement.allowed_mimetypes'));
        $maxFiles = config('reimbursement.max_files_per_request');

        return [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
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
