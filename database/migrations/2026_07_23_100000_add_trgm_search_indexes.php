<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Index pencarian trigram (GIN) untuk kolom yang dicari dengan ILIKE '%q%'.
 *
 * Pola leading-wildcard tidak bisa memakai index B-tree biasa, sehingga pada
 * tabel yang bertumbuh (reimbursements/payments/users/audit_logs) pencarian
 * berubah menjadi sequential scan. Ekstensi pg_trgm + index GIN gin_trgm_ops
 * membuat ILIKE substring tetap ter-index. Khusus PostgreSQL.
 */
return new class extends Migration
{
    /** Kolom yang di-index: [tabel, kolom, nama_index]. */
    private array $indexes = [
        ['reimbursements', 'reimbursement_number', 'reimbursements_number_trgm'],
        ['reimbursements', 'title', 'reimbursements_title_trgm'],
        ['payments', 'payment_number', 'payments_number_trgm'],
        ['payments', 'reference_number', 'payments_reference_trgm'],
        ['users', 'name', 'users_name_trgm'],
        ['users', 'email', 'users_email_trgm'],
        ['audit_logs', 'description', 'audit_logs_description_trgm'],
    ];

    public function up(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach ($this->indexes as [$table, $column, $name]) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} USING gin ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        foreach ($this->indexes as [, , $name]) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
