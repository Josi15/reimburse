<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index kinerja (Phase 20).
 * (user_id, status): melayani daftar reimbursement ter-scope employee dengan
 * filter status serta agregasi kartu dashboard personal (GROUP BY status
 * WHERE user_id) dalam satu index scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'reimbursements_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table) {
            $table->dropIndex('reimbursements_user_status_index');
        });
    }
};
