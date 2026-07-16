<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `payments` — pembayaran atas reimbursement berstatus finance_approved.
 * - amount  : BIGINT IDR; CHECK > 0 (≤ nominal disetujui divalidasi aplikasi).
 * - status  : enum pending/processing/paid/failed/cancelled.
 * - processed_by : staff Finance pelaksana.
 * - Partial unique index mencegah PEMBAYARAN GANDA: hanya satu payment aktif
 *   (status bukan failed/cancelled) per reimbursement. Ini lapisan pertahanan DB
 *   yang dikombinasikan dengan SELECT ... FOR UPDATE di Phase 11.
 * - Seluruh FK RESTRICT karena menyangkut jejak keuangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30)->unique();         // PAY-2026-000045
            $table->foreignId('reimbursement_id')->constrained('reimbursements')->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('currency', 3)->default('IDR');
            $table->enum('method', ['bank_transfer', 'cash', 'other']);
            $table->enum('status', ['pending', 'processing', 'paid', 'failed', 'cancelled'])->default('pending');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('paid_at');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');

        // Cegah pembayaran ganda: satu payment aktif per reimbursement.
        DB::statement("CREATE UNIQUE INDEX payments_one_active_per_reimbursement
            ON payments (reimbursement_id)
            WHERE status NOT IN ('failed', 'cancelled') AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
