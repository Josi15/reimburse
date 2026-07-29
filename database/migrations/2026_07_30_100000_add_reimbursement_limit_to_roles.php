<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plafon reimbursement per jabatan (role). `reimbursement_limit` = batas nominal
 * per pengajuan berdasarkan role (IDR, rupiah penuh):
 *   - NULL  = tanpa batas (mis. super_admin)
 *   - 0     = tidak boleh mengajukan
 *   - > 0   = plafon
 * Plafon efektif per pengajuan = yang paling ketat antara plafon role & kategori.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->bigInteger('reimbursement_limit')->nullable()->after('description');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles ADD CONSTRAINT roles_reimbursement_limit_nonneg CHECK (reimbursement_limit IS NULL OR reimbursement_limit >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_reimbursement_limit_nonneg');
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('reimbursement_limit');
        });
    }
};
