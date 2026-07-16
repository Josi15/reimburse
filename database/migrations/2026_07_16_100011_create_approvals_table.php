<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `approvals` — riwayat setiap tindakan persetujuan (membentuk timeline).
 * Satu reimbursement bisa memiliki banyak baris (Manager approve, Finance reject,
 * minta revisi, dst). `notes` wajib diisi aplikasi bila action = rejected.
 * - reimbursement_id : CASCADE (histori ikut induk).
 * - approver_id      : RESTRICT (user penyetuju tak boleh hilang dari jejak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reimbursement_id')->constrained('reimbursements')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->enum('level', ['manager', 'finance']);
            $table->enum('action', ['approved', 'rejected', 'revision_requested']);
            $table->text('notes')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
