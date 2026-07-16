<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\ReplaceAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manajemen file terpusat (Phase 16). Download, preview, replace, delete —
 * otorisasi mengikuti policy entitas induk (view untuk baca, update untuk ubah).
 */
class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $service) {}

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorizeView($attachment);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->file_path), 404, 'File tidak ditemukan.');

        return $disk->download($attachment->file_path, $attachment->file_name);
    }

    public function preview(Attachment $attachment): StreamedResponse
    {
        $this->authorizeView($attachment);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->file_path), 404, 'File tidak ditemukan.');

        // Inline (untuk preview gambar/PDF di browser).
        return $disk->response($attachment->file_path, $attachment->file_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function replace(ReplaceAttachmentRequest $request, Attachment $attachment): AttachmentResource
    {
        $this->authorizeModify($attachment);

        $updated = $this->service->replace($attachment, $request->file('file'), $request->user());

        return new AttachmentResource($updated);
    }

    public function destroy(Attachment $attachment): Response
    {
        $this->authorizeModify($attachment);

        $this->service->delete($attachment);

        return response()->noContent();
    }

    /** Baca file → butuh izin 'view' pada entitas induk. */
    private function authorizeView(Attachment $attachment): void
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404, 'Entitas induk tidak ditemukan.');

        $this->authorize('view', $parent);
    }

    /** Ubah/hapus file → butuh izin 'update' pada entitas induk. */
    private function authorizeModify(Attachment $attachment): void
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404, 'Entitas induk tidak ditemukan.');

        $this->authorize('update', $parent);
    }
}
