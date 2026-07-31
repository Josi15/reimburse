<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tahap persetujuan Direksi untuk pengajuan bernilai besar.
 *
 * Alur menjadi: Manager → Finance → (Direksi, bila nominal di atas ambang) →
 * dibayar. Ambangnya di config/reimbursement.php, bukan di DB, agar bisa
 * berbeda per environment tanpa migrasi baru.
 *
 * Dua CHECK constraint diperluas:
 * - reimbursements.status : + director_approved, director_rejected
 * - approvals.level       : + director
 * Data lama tetap valid karena nilai yang sudah ada tidak diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reimbursements DROP CONSTRAINT IF EXISTS reimbursements_status_check');
        DB::statement(
            "ALTER TABLE reimbursements ADD CONSTRAINT reimbursements_status_check
             CHECK (status IN (
                'draft', 'submitted',
                'manager_approved', 'manager_rejected',
                'finance_approved', 'finance_rejected',
                'director_approved', 'director_rejected',
                'revision_requested', 'paid'
             ))"
        );

        DB::statement('ALTER TABLE approvals DROP CONSTRAINT IF EXISTS approvals_level_check');
        DB::statement(
            "ALTER TABLE approvals ADD CONSTRAINT approvals_level_check
             CHECK (level IN ('manager', 'finance', 'director'))"
        );
    }

    public function down(): void
    {
        // Kembalikan baris bertahap-Direksi ke status setara agar constraint
        // lama bisa dipasang lagi tanpa menolak data yang sudah ada.
        DB::table('reimbursements')->where('status', 'director_approved')->update(['status' => 'finance_approved']);
        DB::table('reimbursements')->where('status', 'director_rejected')->update(['status' => 'finance_rejected']);
        DB::table('approvals')->where('level', 'director')->update(['level' => 'finance']);

        DB::statement('ALTER TABLE reimbursements DROP CONSTRAINT IF EXISTS reimbursements_status_check');
        DB::statement(
            "ALTER TABLE reimbursements ADD CONSTRAINT reimbursements_status_check
             CHECK (status IN (
                'draft', 'submitted',
                'manager_approved', 'manager_rejected',
                'finance_approved', 'finance_rejected',
                'revision_requested', 'paid'
             ))"
        );

        DB::statement('ALTER TABLE approvals DROP CONSTRAINT IF EXISTS approvals_level_check');
        DB::statement(
            "ALTER TABLE approvals ADD CONSTRAINT approvals_level_check
             CHECK (level IN ('manager', 'finance'))"
        );
    }
};
