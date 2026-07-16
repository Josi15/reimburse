<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['boolean'],
            'role_ids' => ['sometimes', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
        ];
    }
}
