<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghitung percobaan salah saat memasukkan kode reset.
 *
 * Kode 6 digit hanya punya sejuta kemungkinan — jauh lebih lemah dari token 64
 * karakter hex yang dipakai sebelumnya, dan tanpa batas percobaan seluruh ruang
 * itu bisa disapu skrip jauh lebih cepat daripada masa berlaku kodenya. Kolom
 * ini yang membuat penebakan berhenti sebelum sempat sampai ke sana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn('attempts');
        });
    }
};
