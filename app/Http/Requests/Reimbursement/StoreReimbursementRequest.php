<?php

namespace App\Http\Requests\Reimbursement;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReimbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via policy (create) di controller.
    }

    public function rules(): array
    {
        $maxKb = config('reimbursement.max_file_size_kb');
        $mimes = implode(',', config('reimbursement.allowed_mimes'));
        $mimetypes = implode(',', config('reimbursement.allowed_mimetypes'));
        $maxFiles = config('reimbursement.max_files_per_request');

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
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

    /** Nominal tidak boleh melebihi plafon kategori (bila diset). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $category = Category::find($this->input('category_id'));
            if ($category && $category->max_amount !== null && (int) $this->input('amount') > $category->max_amount) {
                $validator->errors()->add('amount',
                    'Nominal melebihi plafon kategori (Rp '.number_format($category->max_amount, 0, ',', '.').').');
            }
        });
    }
}
