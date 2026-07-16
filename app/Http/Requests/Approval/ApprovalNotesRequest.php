<?php

namespace App\Http\Requests\Approval;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Digunakan untuk reject & request-revision: catatan/alasan WAJIB diisi.
 * (Approve memakai validasi terpisah dengan notes opsional.)
 */
class ApprovalNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via policy di controller.
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Alasan wajib diisi.',
        ];
    }
}
