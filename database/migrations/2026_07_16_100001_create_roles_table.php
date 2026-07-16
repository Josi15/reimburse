<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `roles` — daftar peran RBAC.
 * Berisi 6 role kanonik (di-seed Phase 5): super_admin, admin, employee,
 * manager, finance, auditor. `name` = slug unik, `display_name` = label tampilan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();            // slug, mis. "manager"
            $table->string('display_name', 100);             // mis. "Manager"
            $table->string('guard_name', 50)->default('web');
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
