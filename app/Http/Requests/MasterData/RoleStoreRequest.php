<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Slug role hanya boleh huruf kecil, angka, dan underscore.',
        ];
    }
}
