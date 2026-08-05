<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetCode;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Batas waktu mengetik password baru, terhitung sejak kode dicocokkan.
     *
     * Lebih longgar dari masa berlaku kode karena risikonya sudah berbeda:
     * penebakan kode sudah lewat, yang tersisa hanya sesi yang ditinggal
     * terbuka. Tetap dibatasi supaya halaman yang lupa ditutup di komputer
     * bersama tidak selamanya bisa dipakai mengganti password.
     */
    private const VERIFIED_WINDOW_MINUTES = 10;

    public function __construct(private readonly PasswordResetCode $codes) {}

    /** Halaman pembuatan password baru. */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->verifiedEmail($request)) {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->session()->get(PasswordResetCode::SESSION_EMAIL),
        ]);
    }

    /**
     * Simpan password baru.
     *
     * Akun yang diubah ditentukan dari session yang sudah lolos verifikasi
     * kode, bukan dari field email di form. Alamat yang dikirim browser bisa
     * diganti pengguna, dan mengizinkannya berarti kode yang dikirim ke alamat
     * sendiri bisa dipakai mengganti password akun milik orang lain.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = $this->verifiedEmail($request);

        if (! $email) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Sesi reset sudah berakhir. Silakan minta kode baru.',
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('password.request');
        }

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'remember_token' => Str::random(60),
        ])->save();

        // Kode dihapus supaya tidak bisa dipakai kedua kali, dan seluruh jejak
        // resetnya dibuang dari session bersama ID sesinya.
        $this->codes->forget($email);
        $request->session()->forget([
            PasswordResetCode::SESSION_EMAIL,
            PasswordResetCode::SESSION_VERIFIED_AT,
        ]);
        $request->session()->regenerate();

        // Membuka kunci akun yang tergembok karena salah password berkali-kali
        // menempel di event ini, jadi event-nya wajib tetap dilepas.
        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', __('passwords.reset'));
    }

    /** Email yang sesinya sudah lolos verifikasi kode, atau null bila tidak. */
    private function verifiedEmail(Request $request): ?string
    {
        $email = $request->session()->get(PasswordResetCode::SESSION_EMAIL);
        $verifiedAt = $request->session()->get(PasswordResetCode::SESSION_VERIFIED_AT);

        if (! $email || ! $verifiedAt) {
            return null;
        }

        if (Carbon::createFromTimestamp($verifiedAt)->addMinutes(self::VERIFIED_WINDOW_MINUTES)->isPast()) {
            return null;
        }

        return $email;
    }
}
