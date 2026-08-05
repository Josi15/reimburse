<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

/**
 * Cetak tautan reset password langsung ke terminal, tanpa lewat email.
 *
 * Berguna saat SMTP belum siap (kredensial belum ada, port diblokir, jaringan
 * kantor menahan 587): alur reset tetap bisa diuji ujung ke ujung memakai token
 * asli dari password broker — sama persis dengan yang akan dikirim lewat email.
 *
 * Perintah ini MENOLAK jalan di produksi. Tautan yang dicetaknya setara password
 * sementara, dan mencetaknya ke terminal berarti ia mendarat di scrollback,
 * rekaman sesi, dan log CI. Di mesin dev itu risiko yang wajar; di produksi ia
 * berubah jadi cara mengambil alih akun mana pun tanpa jejak di kotak masuk
 * korban. Pembatasan ini bukan sekadar formalitas — perintah artisan gampang
 * ikut ter-deploy tanpa ada yang memikirkannya lagi.
 */
class ShowPasswordResetLink extends Command
{
    protected $signature = 'password:link {email : Email akun yang mau direset}';

    protected $description = 'Cetak tautan reset password ke terminal (hanya di luar produksi)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Ditolak: perintah ini tidak boleh dipakai di produksi.');
            $this->line('Tautan reset setara password sementara — di produksi kirimkan lewat email.');

            return self::FAILURE;
        }

        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error('Tidak ada akun dengan email '.$email.'.');

            return self::FAILURE;
        }

        $token = Password::broker()->createToken($user);
        $menit = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $this->newLine();
        $this->line('Akun  : '.$user->name.' <'.$user->email.'>');
        $this->line('Berlaku: '.$menit.' menit sejak sekarang');
        $this->newLine();
        $this->info(route('password.reset', ['token' => $token]).'?email='.urlencode($user->email));
        $this->newLine();

        return self::SUCCESS;
    }
}
