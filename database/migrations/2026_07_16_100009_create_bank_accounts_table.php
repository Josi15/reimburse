<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `bank_accounts` — rekening bank milik karyawan (boleh lebih dari satu).
 * - user_id : CASCADE (rekening ikut terhapus bila user dihapus permanen).
 * - bank_id : RESTRICT (bank master tak boleh dihapus bila masih dipakai).
 * - UNIQUE (user_id, bank_id, account_number) mencegah rekening ganda.
 * - Partial unique index menjamin HANYA SATU rekening utama aktif per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained('banks')->restrictOnDelete();
            $table->string('account_number', 50);
            $table->string('account_holder_name', 100);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'bank_id', 'account_number'], 'bank_accounts_user_bank_number_unique');
        });

        // Satu rekening utama per user (abaikan yang sudah soft-deleted).
        DB::statement('CREATE UNIQUE INDEX bank_accounts_one_primary_per_user
            ON bank_accounts (user_id)
            WHERE is_primary = true AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
