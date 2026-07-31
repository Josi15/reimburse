<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis pengajuan (App\Enums\ClaimType) + detail spesifik per jenis.
 *
 * - claim_type : VARCHAR + CHECK (konsisten dengan enum lain di proyek ini).
 *                Default 'expense' agar seluruh data lama tetap valid.
 * - details    : JSONB berisi field tambahan sesuai jenis (nama barang, jam
 *                lembur, spesifikasi server, dst). Skema field-nya dijaga di
 *                level aplikasi oleh ClaimType, bukan di DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->string('claim_type', 20)->default('expense')->after('reimbursement_number');
            $table->jsonb('details')->nullable()->after('reason');
        });

        DB::statement(
            "ALTER TABLE reimbursements ADD CONSTRAINT reimbursements_claim_type_check
             CHECK (claim_type IN ('expense', 'goods', 'service', 'overtime'))"
        );

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->index(['claim_type', 'status']);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reimbursements DROP CONSTRAINT IF EXISTS reimbursements_claim_type_check');

        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropIndex(['claim_type', 'status']);
            $table->dropColumn(['claim_type', 'details']);
        });
    }
};
