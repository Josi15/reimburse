<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via PaymentPolicy di controller.
    }

    public function rules(): array
    {
        $maxKb = config('reimbursement.max_file_size_kb');
        $mimes = implode(',', config('reimbursement.allowed_mimes'));

        return [
            'method' => ['required', new Enum(PaymentMethod::class)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'bank_account_id' => ['nullable', 'integer', Rule::exists('bank_accounts', 'id')],
            'proof' => ['nullable', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
        ];
    }
}
