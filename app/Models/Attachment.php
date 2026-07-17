<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Attachment — file terpusat (polymorphic) untuk bukti reimbursement & payment.
 */
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'disk',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** URL sementara/publik untuk mengakses file sesuai disk-nya. */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk($this->disk)->url($this->file_path));
    }

    /** Ukuran file dalam format terbaca (mis. "1.2 MB"). */
    protected function humanSize(): Attribute
    {
        return Attribute::get(function () {
            $bytes = (int) $this->file_size;
            $units = ['B', 'KB', 'MB', 'GB'];
            $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
            $i = min($i, count($units) - 1);

            return round($bytes / (1024 ** $i), 2).' '.$units[$i];
        });
    }
}
