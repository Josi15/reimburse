<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `reimbursements` — entitas inti pengajuan penggantian biaya.
 * - status : enum state machine (Phase 1/9). Di PostgreSQL, enum() Laravel
 *            menghasilkan VARCHAR + CHECK constraint.
 * - amount : BIGINT satuan rupiah penuh (IDR); CHECK > 0.
 * - bank_account_id : NULL saat draft, wajib saat submit (divalidasi aplikasi).
 * - department_id   : snapshot department pengaju (tetap FK → tetap 3NF).
 * - FK transaksi memakai RESTRICT agar master/user tak terhapus selama ada jejak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursements', function (Blueprint $table) {
            $table->id();
            $table->string('reimbursement_number', 30)->unique();   // RMB-2026-000123
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->text('reason');                                 // alasan wajib
            $table->bigInteger('amount');                           // IDR, rupiah penuh
            $table->string('currency', 3)->default('IDR');
            $table->enum('status', [
                'draft', 'submitted',
                'manager_approved', 'manager_rejected',
                'finance_approved', 'finance_rejected',
                'revision_requested', 'paid',
            ])->default('draft');
            $table->date('expense_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();          // saat Paid
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'department_id']);             // dashboard Manager/Finance
            $table->index('submitted_at');
        });

        DB::statement('ALTER TABLE reimbursements ADD CONSTRAINT reimbursements_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursements');
    }
};
