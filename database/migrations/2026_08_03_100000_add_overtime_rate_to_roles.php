<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upah lembur per jam menurut jabatan (IDR, rupiah penuh):
 *   - NULL = jabatan ini tidak berhak mengajukan lembur
 *   - > 0  = tarif per jam
 *
 * Disimpan di `roles` (bukan konstanta di kode) agar tarifnya bisa disesuaikan
 * lewat menu Master tanpa deploy, sejajar dengan reimbursement_limit.
 * Tarif efektif seorang user = yang tertinggi di antara role yang ia pegang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->bigInteger('overtime_rate')->nullable()->after('reimbursement_limit');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles ADD CONSTRAINT roles_overtime_rate_positive CHECK (overtime_rate IS NULL OR overtime_rate > 0)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_overtime_rate_positive');
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('overtime_rate');
        });
    }
};
