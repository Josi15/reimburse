<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Layanan file terpusat untuk entitas polymorphic (Reimbursement, Payment).
 * Menyimpan file ke disk default (local dev / s3 prod, sesuai Phase 3) dan
 * membuat record Attachment. Phase 16 memperluas dengan preview/replace, dll.
 */
class AttachmentService
{
    /** Simpan satu file dan lampirkan ke model. */
    public function store(UploadedFile $file, Model $attachable, User $uploader, ?string $folder = null): Attachment
    {
        $disk = config('filesystems.default');
        $folder ??= $this->folderFor($attachable);

        $path = $file->store($folder, $disk);

        return $attachable->attachments()->create([
            'uploaded_by' => $uploader->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    /** Simpan banyak file sekaligus. */
    public function storeMany(iterable $files, Model $attachable, User $uploader, ?string $folder = null): array
    {
        $stored = [];
        foreach ($files as $file) {
            $stored[] = $this->store($file, $attachable, $uploader, $folder);
        }

        return $stored;
    }

    /** Ganti file pada attachment yang sama (hapus lama, simpan baru). */
    public function replace(Attachment $attachment, UploadedFile $file, User $user): Attachment
    {
        $disk = $attachment->disk;
        Storage::disk($disk)->delete($attachment->file_path);

        $folder = dirname($attachment->file_path);
        $path = $file->store($folder, $disk);

        $attachment->update([
            'uploaded_by' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return $attachment;
    }

    /** Hapus file fisik + record. */
    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->file_path);
        $attachment->delete();
    }

    private function folderFor(Model $attachable): string
    {
        $base = str($attachable->getTable())->lower();

        return "attachments/{$base}/{$attachable->getKey()}";
    }
}
