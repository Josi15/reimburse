<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo awal rekening perusahaan saat didaftarkan ke sistem (IDR). Menjadi
 * titik mula perhitungan buku kas: saldo = opening_balance + pemasukan − pengeluaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->bigInteger('opening_balance')->default(0)->after('account_holder_name');
        });
    }

    public function down(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }
};
