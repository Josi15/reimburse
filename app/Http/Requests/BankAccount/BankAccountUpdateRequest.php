<?php

namespace App\Http\Requests\BankAccount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankAccountUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['sometimes', 'required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'account_number' => ['sometimes', 'required', 'string', 'regex:/^[0-9]{6,30}$/',
                Rule::unique('bank_accounts', 'account_number')
                    ->where('user_id', $this->user()->id)
                    ->where('bank_id', $this->input('bank_id', $this->route('bank_account')?->bank_id))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('bank_account'))],
            'account_holder_name' => ['sometimes', 'required', 'string', 'max:100'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.regex' => 'Nomor rekening harus 6–30 digit angka.',
        ];
    }
}
