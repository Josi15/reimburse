<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi via policy parent di controller.
    }

    public function rules(): array
    {
        $maxKb = config('reimbursement.max_file_size_kb');
        $mimes = implode(',', config('reimbursement.allowed_mimes'));
        $mimetypes = implode(',', config('reimbursement.allowed_mimetypes'));

        return [
            'file' => ['required', 'file', "mimes:{$mimes}", "mimetypes:{$mimetypes}", "max:{$maxKb}"],
        ];
    }
}
