<?php

namespace App\Notifications\Concerns;

/**
 * Memisahkan koneksi queue per channel notifikasi.
 *
 * Masalahnya: seluruh notifikasi di sini `ShouldQueue`, sehingga saat
 * QUEUE_CONNECTION=database TIDAK ADA notifikasi yang benar-benar terkirim
 * sampai `php artisan queue:work` dijalankan — lonceng in-app tetap kosong.
 *
 * Solusinya: channel `database` (notifikasi in-app) dieksekusi pada koneksi
 * `sync` sehingga langsung tersimpan begitu aksi selesai, sedangkan channel
 * `mail` — satu-satunya yang benar-benar lambat karena memanggil SMTP — tetap
 * masuk antrean dan dikerjakan worker.
 */
trait DeliversInAppImmediately
{
    /** @return array<string, string> */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail' => config('queue.default'),
        ];
    }
}
