<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordCodeNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Kode reset password 6 digit: pembuatan, pengiriman, dan verifikasinya.
 *
 * Kode sependek ini hanya punya sejuta kemungkinan, jadi keamanannya tidak
 * datang dari panjangnya melainkan dari tiga batas yang bekerja bersamaan:
 *
 *   1. Umur pendek (config auth.passwords.*.expire) — ruang tebakan hanya
 *      terbuka selama beberapa menit, bukan selamanya.
 *   2. Batas percobaan (MAX_ATTEMPTS) — kode hangus setelah beberapa tebakan
 *      salah, sehingga penyerang tidak bisa menyapu sejuta kemungkinan itu.
 *   3. Satu kode aktif per akun — meminta kode baru membatalkan yang lama,
 *      supaya tebakan tidak bisa diakumulasi dari banyak kode sekaligus.
 *
 * Hilangkan salah satunya dan sisanya tidak cukup: tanpa batas percobaan,
 * sejuta tebakan bisa diselesaikan skrip jauh lebih cepat daripada masa
 * berlakunya habis.
 *
 * Kodenya sendiri disimpan ter-hash, sama seperti password. Kalau isi database
 * bocor, kode yang masih hidup tetap tidak bisa dipakai mengambil alih akun.
 */
class PasswordResetCode
{
    /** Tebakan salah yang ditoleransi sebelum kode dihanguskan. */
    public const MAX_ATTEMPTS = 5;

    /**
     * Email yang sedang direset disimpan di session, bukan dibawa lewat URL
     * atau field tersembunyi. Nilai yang bolak-balik lewat browser bisa diganti
     * pengguna, dan alamat yang bisa diganti di langkah terakhir berarti kode
     * yang diverifikasi untuk satu akun dipakai mengganti password akun lain.
     */
    public const SESSION_EMAIL = 'password_reset.email';

    /** Penanda bahwa kode sudah dicocokkan, beserta waktunya. */
    public const SESSION_VERIFIED_AT = 'password_reset.verified_at';

    private const TABLE = 'password_reset_tokens';

    /**
     * Terbitkan kode baru untuk sebuah akun dan kirimkan lewat email.
     *
     * Kode lama ditimpa, bukan didampingi: dua kode hidup berarti dua kali
     * peluang tebakan untuk satu akun yang sama.
     */
    public function send(User $user): void
    {
        $user->notify(new ResetPasswordCodeNotification($this->issue($user)));
    }

    /**
     * Terbitkan kode dan kembalikan versi mentahnya.
     *
     * Dipisahkan dari send() hanya demi perkakas dev yang perlu menampilkan
     * kode saat SMTP belum siap. Di jalur normal kode mentah tidak pernah
     * keluar dari kelas ini — yang tersimpan cuma hash-nya.
     */
    public function issue(User $user): string
    {
        $code = $this->generate();

        DB::table(self::TABLE)->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($code),
                'attempts' => 0,
                'created_at' => now(),
            ],
        );

        return $code;
    }

    /**
     * Cocokkan kode yang diketik pengguna.
     *
     * Semua kegagalan — kode tidak ada, kedaluwarsa, salah, atau sudah terlalu
     * sering ditebak — sengaja dilaporkan sebagai satu nilai false yang sama.
     * Membedakannya akan memberi tahu penyerang apakah sebuah email punya kode
     * aktif, dan itu sama saja dengan memberi tahu apakah akunnya ada.
     */
    public function verify(string $email, string $code): bool
    {
        $row = DB::table(self::TABLE)->where('email', $email)->first();

        if (! $row) {
            return false;
        }

        if ($this->expired($row->created_at) || $row->attempts >= self::MAX_ATTEMPTS) {
            $this->forget($email);

            return false;
        }

        if (! Hash::check($code, $row->token)) {
            // Dihitung di database, bukan di memori, supaya beberapa tebakan
            // yang dikirim berbarengan tidak saling menimpa hitungannya.
            DB::table(self::TABLE)->where('email', $email)->increment('attempts');

            if (($row->attempts + 1) >= self::MAX_ATTEMPTS) {
                $this->forget($email);
            }

            return false;
        }

        return true;
    }

    /** Hapus kode; dipakai setelah password benar-benar diganti. */
    public function forget(string $email): void
    {
        DB::table(self::TABLE)->where('email', $email)->delete();
    }

    /**
     * Enam digit dari sumber acak kriptografis.
     *
     * random_int(), bukan rand()/mt_rand(): keluaran generator biasa bisa
     * diprediksi dari beberapa nilai sebelumnya, dan kode yang bisa ditebak
     * tanpa menebak sama saja dengan tidak ada kode.
     */
    private function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function expired(?string $createdAt): bool
    {
        if (! $createdAt) {
            return true;
        }

        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return Carbon::parse($createdAt)->addMinutes($minutes)->isPast();
    }
}
