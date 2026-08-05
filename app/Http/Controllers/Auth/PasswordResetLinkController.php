<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly PasswordResetCode $codes) {}

    /**
     * Halaman permintaan kode reset.
     *
     * Masa berlaku dikirim dari config, tidak ditulis ulang di halamannya.
     * Angka yang disalin ke teks akan diam-diam jadi bohong begitu confignya
     * diubah, dan janji "berlaku sekian menit" yang meleset membuat pengguna
     * menyalahkan sistemnya saat kode mati lebih cepat dari yang dijanjikan.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
        ]);
    }

    /**
     * Terbitkan kode reset dan kirimkan lewat email.
     *
     * Langkah ini SELALU berakhir sama, apa pun yang terjadi di belakang layar:
     * email terdaftar, tidak terdaftar, atau SMTP-nya mati. Alasannya bukan
     * kerapian, melainkan pencegahan enumerasi akun — begitu satu kasus saja
     * dibedakan (teks lain, error validasi, apalagi HTTP 500), halaman ini
     * berubah jadi alat memeriksa email mana yang punya akun di sini, dan
     * penyerang anonim cukup membaca bedanya untuk memetakan seluruh pengguna.
     *
     * Yang disembunyikan hanya dari pengunjung; kegagalannya tetap masuk log
     * supaya operator masih bisa menanganinya.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->string('email')->toString();
        $user = User::where('email', $email)->first();

        if ($user) {
            try {
                $this->codes->send($user);
            } catch (Throwable $e) {
                // Kegagalan transport (SMTP mati, kredensial salah) hanya bisa
                // terjadi pada email yang terdaftar. Kalau dibiarkan naik jadi
                // HTTP 500, selisih 500-vs-302 itu sendiri sudah membocorkan
                // keberadaan akun.
                Log::error('Pengiriman kode reset password gagal.', [
                    'email' => $email,
                    'ip' => $request->ip(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        // Email disimpan di session walau akunnya tidak ada, supaya langkah
        // berikutnya terlihat persis sama bagi siapa pun.
        $request->session()->put(PasswordResetCode::SESSION_EMAIL, $email);
        $request->session()->forget(PasswordResetCode::SESSION_VERIFIED_AT);

        return redirect()->route('password.code');
    }
}
