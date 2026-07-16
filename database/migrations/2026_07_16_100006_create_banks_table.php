<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `banks` — master data bank.
 * Di-seed Phase 5 dengan BCA, BRI, BNI, Mandiri, SeaBank.
 * `code` unik (mis. BCA). Soft delete agar rekening/pembayaran lama tetap valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                     // "Bank Central Asia"
            $table->string('code', 20)->unique();            // "BCA"
            $table->string('swift_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
