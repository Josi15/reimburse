<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `projects` — master data proyek. Reimbursement dapat dikaitkan ke
 * sebuah proyek (opsional) agar pengeluaran per proyek bisa direkap.
 * `budget` = anggaran opsional (IDR); NULL berarti tak dianggarkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();            // mis. PRJ-2026-001
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->bigInteger('budget')->nullable();         // anggaran IDR, null = bebas
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_budget_positive CHECK (budget IS NULL OR budget > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
