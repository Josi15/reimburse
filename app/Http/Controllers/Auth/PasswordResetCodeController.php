<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetCodeController extends Controller
{
    public function __construct(private readonly PasswordResetCode $codes) {}

    /** Halaman pengisian kode 6 digit. */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has(PasswordResetCode::SESSION_EMAIL)) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/VerifyResetCode', [
            'email' => $request->session()->get(PasswordResetCode::SESSION_EMAIL),
            'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            'maxAttempts' => PasswordResetCode::MAX_ATTEMPTS,
        ]);
    }

    /**
     * Cocokkan kode yang diketik.
     *
     * Alamat email diambil dari session, bukan dari form. Kalau ikut dikirim
     * browser, pengguna bisa menggantinya di langkah ini dan memakai kode yang
     * dikirim ke alamatnya sendiri untuk mengganti password akun orang lain.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $email = $request->session()->get(PasswordResetCode::SESSION_EMAIL);

        if (! $email) {
            return redirect()->route('password.request');
        }

        if (! $this->codes->verify($email, $request->string('code')->toString())) {
            Log::info('Kode reset password ditolak.', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            // Satu pesan untuk semua sebab: kode salah, kedaluwarsa, sudah
            // hangus karena terlalu sering ditebak, atau memang tidak pernah
            // ada karena emailnya tak terdaftar. Membedakannya akan memberi
            // tahu penyerang mana email yang benar-benar punya akun.
            throw ValidationException::withMessages([
                'code' => ['Kode tidak cocok atau sudah tidak berlaku. Minta kode baru bila perlu.'],
            ]);
        }

        // ID session diganti setelah kode terbukti benar. Tanpa ini, penyerang
        // yang sempat menanamkan session ID ke browser korban ikut terbawa naik
        // ke sesi yang sudah terverifikasi.
        $request->session()->regenerate();
        $request->session()->put(PasswordResetCode::SESSION_EMAIL, $email);
        $request->session()->put(PasswordResetCode::SESSION_VERIFIED_AT, now()->timestamp);

        return redirect()->route('password.reset');
    }
}
