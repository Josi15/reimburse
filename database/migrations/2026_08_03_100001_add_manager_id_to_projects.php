<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `projects.manager_id` — pemegang proyek (Project Manager). Dialah yang
 * bertanggung jawab atas anggaran proyek dan berhak melihat sisa dananya.
 * Nullable: proyek lama / proyek yang belum ditugaskan tetap valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('description')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });
    }
};
