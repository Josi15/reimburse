<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pembuat nomor urut per-prefix (mis. RMB-2026-000123) yang aman-balapan.
 *
 * Saat berjalan di dalam transaksi (create draft / proses pembayaran), sebuah
 * PostgreSQL advisory lock per-prefix diserialisasi sampai transaksi commit,
 * sehingga dua insert bersamaan tidak membaca MAX yang sama lalu menghasilkan
 * nomor duplikat. Partial unique index tetap menjadi lapisan pertahanan akhir.
 */
class SequentialNumber
{
    public static function next(string $model, string $column, string $prefix, int $pad = 6): string
    {
        // Serialisasi alokasi nomor untuk prefix ini (khusus PostgreSQL).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [crc32($prefix)]);
        }

        /** @var Model $model */
        $last = $model::withTrashed()
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc($column)
            ->value($column);

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, $pad, '0', STR_PAD_LEFT);
    }
}
