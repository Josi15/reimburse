<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `company_account_deposits` — PEMASUKAN (top-up dana) ke rekening
 * perusahaan. Bersama pembayaran (pengeluaran, dari tabel payments) membentuk
 * buku kas per rekening/bulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_account_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_bank_account_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->date('deposited_at');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_bank_account_id', 'deposited_at']);
        });

        DB::statement('ALTER TABLE company_account_deposits ADD CONSTRAINT company_account_deposits_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('company_account_deposits');
    }
};
