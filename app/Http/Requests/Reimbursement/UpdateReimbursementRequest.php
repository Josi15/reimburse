<?php

namespace App\Http\Requests\Reimbursement;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReimbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via policy (update) di controller.
    }

    public function rules(): array
    {
        $maxKb = config('reimbursement.max_file_size_kb');
        $mimes = implode(',', config('reimbursement.allowed_mimes'));
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
            'attachments.*' => ['file', "mimes:{$mimes}", "max:{$maxKb}"],
            'delete_attachment_ids' => ['nullable', 'array'],
            'delete_attachment_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('category_id') || ! $this->filled('amount')) {
                return;
            }
            $category = Category::find($this->input('category_id'));
            if ($category && $category->max_amount !== null && (int) $this->input('amount') > $category->max_amount) {
                $validator->errors()->add('amount',
                    'Nominal melebihi plafon kategori (Rp '.number_format($category->max_amount, 0, ',', '.').').');
            }
        });
    }
}
