<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `company_bank_accounts` — rekening milik PERUSAHAAN yang dipakai
 * Finance sebagai SUMBER pembayaran reimbursement. Berbeda dari `bank_accounts`
 * (rekening pribadi karyawan / tujuan transfer). `label` mis. "Kas Operasional".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('banks');
            $table->string('label', 100);                    // nama rekening internal
            $table->string('account_number', 40);
            $table->string('account_holder_name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
    }
};
