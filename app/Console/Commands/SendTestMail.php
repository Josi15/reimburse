<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Uji konfigurasi email tanpa harus lewat alur "lupa password".
 *
 * Salah konfigurasi SMTP biasanya baru ketahuan saat pengguna sudah menunggu di
 * halaman reset, dan penyebabnya tenggelam di balik pesan seperti "535 Username
 * and Password not accepted" yang tidak memberi tahu apa yang harus diperbaiki.
 * Perintah ini memeriksa dulu hal-hal yang paling sering salah, menyebutkannya
 * dengan jelas, lalu barulah mencoba mengirim.
 */
class SendTestMail extends Command
{
    protected $signature = 'mail:test {email : Alamat tujuan}';

    protected $description = 'Kirim email percobaan untuk memastikan konfigurasi MAIL_* sudah benar';

    public function handle(): int
    {
        $to = $this->argument('email');
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');
        $from = config('mail.from.address');

        $this->line('Mailer  : '.$mailer);
        $this->line('Host    : '.$host.':'.config('mail.mailers.smtp.port'));
        $this->line('Dari    : '.$from);
        $this->line('Tujuan  : '.$to);
        $this->newLine();

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log — email TIDAK dikirim ke mana pun.');
            $this->line('Isinya hanya ditulis ke storage/logs/laravel.log.');
            $this->line('Supaya benar-benar terkirim, set MAIL_MAILER=smtp di .env.');
            $this->newLine();
        }

        if ($mailer === 'smtp' && ! $this->smtpSiap($host, $username, $password, $from)) {
            return self::FAILURE;
        }

        try {
            Mail::raw(
                'Ini email percobaan dari '.config('app.name').".\n\n".
                'Kalau email ini sampai, konfigurasi MAIL_* sudah benar dan fitur lupa password siap dipakai.',
                fn ($message) => $message->to($to)->subject('Tes Konfigurasi Email '.config('app.name')),
            );
        } catch (Throwable $e) {
            $this->error('Gagal mengirim: '.$e->getMessage());
            $this->jelaskanKegagalan($e->getMessage());

            return self::FAILURE;
        }

        $this->info($mailer === 'log'
            ? 'Selesai. Cek storage/logs/laravel.log.'
            : 'Terkirim. Cek inbox (dan folder spam) '.$to.'.');

        return self::SUCCESS;
    }

    /** Periksa kesalahan yang paling sering terjadi sebelum menyentuh jaringan. */
    private function smtpSiap(?string $host, ?string $username, ?string $password, ?string $from): bool
    {
        $gmail = str_contains((string) $host, 'gmail');

        if (blank($password)) {
            $this->error('MAIL_PASSWORD di .env masih kosong — server email pasti menolak.');
            $this->newLine();

            if ($gmail) {
                $this->line('Gmail tidak menerima password akun biasa. Yang dibutuhkan App Password:');
                $this->line('  1. Aktifkan Verifikasi 2 Langkah di https://myaccount.google.com/security');
                $this->line('  2. Buat App Password di https://myaccount.google.com/apppasswords');
                $this->line('  3. Salin 16 hurufnya, HAPUS SPASINYA, isi ke MAIL_PASSWORD di .env');
                $this->newLine();
                $this->line('Kalau halaman apppasswords tidak bisa dibuka (akun sekolah/kantor sering');
                $this->line('memblokirnya), pakai penyedia lain seperti Brevo atau Mailtrap yang');
                $this->line('memberi kredensial SMTP langsung.');
            }

            return false;
        }

        if ($password !== trim($password) || str_contains($password, ' ')) {
            $this->error('MAIL_PASSWORD mengandung spasi. App Password harus ditulis 16 huruf tanpa spasi.');

            return false;
        }

        if ($gmail && $username !== $from) {
            // Gmail menolak mengirim atas nama alamat yang bukan miliknya dan
            // diam-diam menggantinya, sehingga hasilnya membingungkan.
            $this->warn('MAIL_FROM_ADDRESS ('.$from.') berbeda dari MAIL_USERNAME ('.$username.').');
            $this->warn('Gmail akan menimpanya. Samakan keduanya agar tidak membingungkan.');
            $this->newLine();
        }

        if ($gmail && strlen($password) !== 16) {
            $this->warn('App Password Gmail biasanya 16 huruf; punya Anda '.strlen($password).'.');
            $this->warn('Kalau gagal, pastikan yang disalin App Password, bukan password akun.');
            $this->newLine();
        }

        return true;
    }

    /** Terjemahkan pesan server yang paling sering muncul ke tindakan nyata. */
    private function jelaskanKegagalan(string $pesan): void
    {
        $this->newLine();

        if (str_contains($pesan, '535') || str_contains($pesan, 'BadCredentials')) {
            $this->line('Server menolak kredensialnya. Yang biasanya jadi sebab:');
            $this->line('  - MAIL_PASSWORD bukan App Password, melainkan password akun biasa');
            $this->line('  - App Password disalin beserta spasinya');
            $this->line('  - MAIL_USERNAME bukan alamat Gmail lengkap');

            return;
        }

        if (str_contains($pesan, 'Connection could not be established') || str_contains($pesan, 'timed out')) {
            $this->line('Tidak bisa menyambung ke server. Yang biasanya jadi sebab:');
            $this->line('  - Port 587 diblokir jaringan; coba MAIL_PORT=465 dan MAIL_SCHEME=smtps');
            $this->line('  - MAIL_HOST salah ketik');
        }
    }
}
