<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memperluas tabel `users` bawaan Breeze dengan kolom domain aplikasi.
 * - department_id : FK ke departments (SET NULL bila dept dihapus).
 * - manager_id    : self-reference ke atasan langsung (SET NULL).
 * - phone, is_active                      : profil & status akun.
 * - failed_login_attempts, locked_until   : mendukung login attempt limit (Phase 7).
 * - softDeletes   : user dapat dinonaktifkan tanpa menghapus jejak keuangannya.
 * Catatan: tidak memakai after() karena PostgreSQL tidak mendukung penempatan kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['manager_id']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'department_id', 'manager_id', 'phone', 'is_active',
                'failed_login_attempts', 'locked_until', 'deleted_at',
            ]);
        });
    }
};
