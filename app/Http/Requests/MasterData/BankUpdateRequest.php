<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => ['sometimes', 'required', 'string', 'max:20',
                Rule::unique('banks', 'code')->ignore($this->route('bank'))],
            'swift_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ];
    }
}
