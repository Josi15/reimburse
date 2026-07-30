<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepositStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via middleware permission:company_account.manage.
    }

    public function rules(): array
    {
        return [
            'company_bank_account_id' => ['required', 'integer',
                Rule::exists('company_bank_accounts', 'id')->where('is_active', true)],
            'amount' => ['required', 'integer', 'min:1'],
            'deposited_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
