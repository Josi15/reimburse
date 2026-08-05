<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Uji cepat konfigurasi email tanpa harus lewat alur "lupa password".
 *
 * Kesalahan konfigurasi SMTP biasanya baru ketahuan saat pengguna sudah
 * menunggu di halaman lupa password, dan pesannya tenggelam di log. Perintah
 * ini memunculkan penyebabnya langsung di terminal.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {email : Alamat tujuan}';

    protected $description = 'Kirim email percobaan untuk memastikan konfigurasi MAIL_* sudah benar';

    public function handle(): int
    {
        $to = $this->argument('email');
        $mailer = config('mail.default');

        $this->line('Mailer  : '.$mailer);
        $this->line('Host    : '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
        $this->line('Dari    : '.config('mail.from.address'));
        $this->line('Tujuan  : '.$to);
        $this->newLine();

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log — email TIDAK dikirim ke mana pun, hanya ditulis ke storage/logs/laravel.log.');
        }

        try {
            Mail::raw(
                'Ini email percobaan dari '.config('app.name').".\n\n".
                'Kalau email ini sampai, konfigurasi MAIL_* sudah benar dan fitur lupa password siap dipakai.',
                fn ($message) => $message->to($to)->subject('Tes Konfigurasi Email '.config('app.name')),
            );
        } catch (Throwable $e) {
            $this->error('Gagal mengirim: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($mailer === 'log'
            ? 'Selesai. Cek storage/logs/laravel.log.'
            : 'Terkirim. Cek inbox (dan folder spam) '.$to.'.');

        return self::SUCCESS;
    }
}
