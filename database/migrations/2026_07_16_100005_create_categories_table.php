<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `categories` — master kategori pengeluaran reimbursement.
 * `max_amount` = plafon opsional per kategori (IDR, satuan rupiah penuh);
 * NULL berarti tanpa batas. CHECK memastikan plafon selalu > 0 bila diisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->string('description', 255)->nullable();
            $table->bigInteger('max_amount')->nullable();    // plafon IDR, null = bebas
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Plafon harus positif bila diisi.
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_max_amount_positive CHECK (max_amount IS NULL OR max_amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
