<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     *
     * Masa berlaku dikirim dari config, tidak ditulis ulang di halamannya.
     * Angka yang disalin ke teks akan diam-diam jadi bohong begitu confignya
     * diubah, dan janji "berlaku sekian menit" yang meleset membuat pengguna
     * menyalahkan sistemnya saat tautan mati lebih cepat dari yang dijanjikan.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
        ]);
    }

    /**
     * Kirim tautan reset password.
     *
     * Halaman ini SELALU menjawab hal yang sama, apa pun yang terjadi di balik
     * layar: email terdaftar, tidak terdaftar, permintaannya sedang dibatasi,
     * atau server SMTP-nya mati. Alasannya bukan kerapian pesan, melainkan
     * pencegahan enumerasi akun. Begitu satu kasus saja dibedakan — teks lain,
     * error validasi, apalagi HTTP 500 — form ini berubah jadi alat untuk
     * memeriksa email mana yang punya akun di sini, dan penyerang anonim cukup
     * membaca status code untuk memetakan seluruh daftar pengguna.
     *
     * Yang disembunyikan hanya dari pengunjung; kegagalannya tetap dicatat ke
     * log supaya operator masih bisa menanganinya.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (Throwable $e) {
            // Exception di titik ini praktis selalu kegagalan transport: SMTP
            // mati, kredensial salah, port diblokir. Kalau dibiarkan naik,
            // hasilnya halaman 500 yang HANYA muncul untuk email terdaftar —
            // persis celah yang ditutup di atas, dibuka lagi lewat error.
            Log::error('Pengiriman tautan reset password gagal.', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'exception' => $e->getMessage(),
            ]);

            $status = Password::RESET_LINK_SENT;
        }

        if ($status !== Password::RESET_LINK_SENT) {
            // INVALID_USER dan RESET_THROTTLED sama-sama hanya mungkin terjadi
            // pada kondisi yang membocorkan ada/tidaknya akun, jadi keduanya
            // ikut diseragamkan. Penyalahgunaan tetap tertahan oleh rate limit
            // per-IP di routes/auth.php, yang balasannya (429) tidak bergantung
            // pada terdaftar atau tidaknya email.
            Log::info('Permintaan reset password tidak menghasilkan email.', [
                'status' => $status,
                'ip' => $request->ip(),
            ]);
        }

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
