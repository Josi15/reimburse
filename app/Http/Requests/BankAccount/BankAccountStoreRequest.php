<?php

namespace App\Http\Requests\BankAccount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankAccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'integer', Rule::exists('banks', 'id')->where('is_active', true)],
            'account_number' => ['required', 'string', 'regex:/^[0-9]{6,30}$/',
                // Cegah rekening ganda milik user yang sama pada bank yang sama.
                Rule::unique('bank_accounts', 'account_number')
                    ->where('user_id', $this->user()->id)
                    ->where('bank_id', $this->input('bank_id'))
                    ->whereNull('deleted_at')],
            'account_holder_name' => ['required', 'string', 'max:100'],
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
