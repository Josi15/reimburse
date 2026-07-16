<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `permissions` — hak akses granular yang dipetakan ke role.
 * Contoh: "reimbursement.create", "payment.process", "audit.view".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();           // mis. "payment.process"
            $table->string('display_name', 150)->nullable();
            $table->string('guard_name', 50)->default('web');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
