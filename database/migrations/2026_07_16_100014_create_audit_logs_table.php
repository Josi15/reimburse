<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `audit_logs` — jejak audit generik seluruh sistem (Phase 15).
 * Bersifat APPEND-ONLY: hanya `created_at`, tanpa updated_at/soft delete,
 * agar integritas jejak terjaga.
 * - user_id   : SET NULL (mis. gagal login sebelum terautentikasi → null).
 * - auditable : polymorphic nullable ke entitas yang diubah.
 * - old_values/new_values : JSONB snapshot perubahan (disengaja disimpan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event', ['login', 'logout', 'create', 'update', 'delete', 'approve', 'reject', 'payment']);
            $table->nullableMorphs('auditable');             // auditable_type, auditable_id (+index)
            $table->string('description', 255)->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();    // IPv4/IPv6
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
