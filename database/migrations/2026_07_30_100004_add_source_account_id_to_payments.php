<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekening SUMBER (perusahaan) untuk tiap pembayaran. Nullable agar pembayaran
 * lama tetap valid; ON DELETE SET NULL agar penghapusan rekening perusahaan tak
 * menghapus riwayat pembayaran. Dasar rekap pengeluaran per rekening perusahaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('source_account_id')->nullable()->after('bank_account_id')
                ->constrained('company_bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_account_id');
        });
    }
};
