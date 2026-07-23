<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambahkan nilai 'submit' ke CHECK constraint audit_logs.event.
 *
 * Event "submit" kini dicatat sebagai peristiwa semantik tersendiri (sebelumnya
 * pengajuan teraudit sebagai 'update' generik). Kolom event adalah VARCHAR+CHECK
 * (enum() Laravel di PostgreSQL), jadi constraint-nya perlu diperbarui.
 */
return new class extends Migration
{
    private array $events = [
        'login', 'logout', 'create', 'update', 'delete', 'submit', 'approve', 'reject', 'payment',
    ];

    public function up(): void
    {
        $this->setCheck($this->events);
    }

    public function down(): void
    {
        $this->setCheck(array_values(array_diff($this->events, ['submit'])));
    }

    private function setCheck(array $events): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $list = implode(', ', array_map(fn ($e) => "'{$e}'", $events));

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_event_check');
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_event_check CHECK (event::text = ANY (ARRAY[{$list}]::text[]))");
    }
};
