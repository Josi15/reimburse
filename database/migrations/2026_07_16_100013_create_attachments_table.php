<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `attachments` — file terpusat (polymorphic), dipakai bukti reimbursement
 * dan bukti pembayaran. `morphs('attachable')` membuat attachable_type +
 * attachable_id beserta index gabungannya. `disk` menyimpan tujuan storage
 * (local/s3) sesuai strategi Phase 3, sehingga kode tidak terikat satu disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');                    // attachable_type, attachable_id (+index)
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('file_name');                     // nama asli file
            $table->string('file_path', 500);                // path di disk/S3
            $table->string('disk', 50)->default('local');
            $table->string('mime_type', 100);
            $table->bigInteger('file_size');                 // bytes
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
