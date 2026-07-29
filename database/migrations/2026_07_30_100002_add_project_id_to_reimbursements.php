<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kaitkan reimbursement ke proyek (opsional). NULL = pengeluaran umum.
 * ON DELETE SET NULL agar penghapusan proyek tak menghapus reimbursement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('category_id')
                ->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
