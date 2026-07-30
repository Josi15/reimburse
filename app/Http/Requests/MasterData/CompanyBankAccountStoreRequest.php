<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyBankAccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via middleware permission:company_account.manage.
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'label' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:40', 'regex:/^[0-9]+$/'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'opening_balance' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.regex' => 'Nomor rekening hanya boleh berisi angka.',
        ];
    }
}
