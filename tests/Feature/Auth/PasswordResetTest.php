<?php

use App\Models\User;
use App\Notifications\ResetPasswordCodeNotification;
use App\Services\PasswordResetCode;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Terbitkan kode untuk sebuah akun dan kembalikan versi mentahnya, lalu
 * tempatkan sesi seolah pengguna baru saja meminta kode lewat halaman depan.
 */
function mintaKode(User $user): string
{
    $code = app(PasswordResetCode::class)->issue($user);

    session([PasswordResetCode::SESSION_EMAIL => $user->email]);

    return $code;
}

test('reset password request screen can be rendered', function () {
    $this->get('/forgot-password')->assertStatus(200);
});

test('the request page announces the real lifetime, not a hardcoded one', function () {
    // Halamannya dulu menulis "60 menit" sebagai teks biasa dan tetap begitu
    // setelah confignya dipendekkan — janji yang meleset bikin pengguna
    // menyalahkan sistem saat kodenya mati lebih cepat dari yang tertulis.
    $this->get('/forgot-password')
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ForgotPassword')
            ->where('expireMinutes', config('auth.passwords.users.expire'))
        );
});

test('requesting a code emails one and moves on to the code screen', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect(route('password.code'));

    Notification::assertSentTo($user, ResetPasswordCodeNotification::class);

    $this->get('/forgot-password/verify')
        ->assertInertia(fn ($page) => $page
            ->component('Auth/VerifyResetCode')
            ->where('email', $user->email)
        );
});

test('the emailed code is six digits and stored only as a hash', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordCodeNotification::class, function ($notification) use ($user) {
        $isi = $notification->toMail($user)->render()->toHtml();

        expect($isi)->toMatch('/\b\d{6}\b/')
            ->and($isi)->toContain('berlaku '.config('auth.passwords.users.expire').' menit');

        return true;
    });

    // Kode yang tersimpan apa adanya berarti bocornya database sama dengan
    // bocornya seluruh akun yang sedang mereset password.
    $tersimpan = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

    expect($tersimpan)->not->toMatch('/^\d{6}$/')
        ->and(strlen($tersimpan))->toBeGreaterThan(20);
});

test('an unknown email is answered exactly like a known one', function () {
    // Kalau jawabannya berbeda, halaman ini jadi alat memeriksa email mana yang
    // punya akun di sini tanpa perlu login sama sekali.
    Notification::fake();

    $user = User::factory()->create();

    $dikenal = $this->post('/forgot-password', ['email' => $user->email]);
    $asing = $this->post('/forgot-password', ['email' => 'bukan-pengguna@fundback.test']);

    expect($dikenal->getStatusCode())->toBe($asing->getStatusCode())
        ->and($dikenal->headers->get('Location'))->toBe($asing->headers->get('Location'));

    $dikenal->assertSessionHasNoErrors();
    $asing->assertSessionHasNoErrors();
});

test('a dead SMTP server does not reveal that an email is registered', function () {
    // Kegagalan kirim hanya mungkin terjadi pada email terdaftar; kalau
    // exception-nya naik jadi HTTP 500, selisih 500-vs-302 itu saja sudah cukup
    // untuk memetakan seluruh daftar pengguna.
    $user = User::factory()->create();

    $this->mock(Dispatcher::class)
        ->shouldReceive('send')
        ->andThrow(new TransportException('SMTP auth failed'));

    $dikenal = $this->post('/forgot-password', ['email' => $user->email]);
    $asing = $this->post('/forgot-password', ['email' => 'bukan-pengguna@fundback.test']);

    $dikenal->assertSessionHasNoErrors();
    expect($dikenal->getStatusCode())->toBe($asing->getStatusCode())
        ->and($dikenal->headers->get('Location'))->toBe($asing->headers->get('Location'));
});

test('the code screen is closed to anyone who has not asked for a code', function () {
    $this->get('/forgot-password/verify')->assertRedirect(route('password.request'));
    $this->get('/reset-password')->assertRedirect(route('password.request'));
});

test('the whole flow works: code, then a new password', function () {
    $user = User::factory()->create();
    $passwordLama = $user->password;
    $code = mintaKode($user);

    $this->post('/forgot-password/verify', ['code' => $code])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.reset'));

    $this->get('/reset-password')->assertStatus(200);

    $this->post('/reset-password', [
        'password' => 'Str0ng#Pass1',
        'password_confirmation' => 'Str0ng#Pass1',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('Str0ng#Pass1', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->password)->not->toBe($passwordLama)
        // Kode wajib mati begitu terpakai, kalau tidak ia jadi password kedua
        // yang berlaku sampai masa berlakunya habis.
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

test('a wrong code is refused', function () {
    $user = User::factory()->create();
    $code = mintaKode($user);
    $salah = str_pad((string) ((((int) $code) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    $this->post('/forgot-password/verify', ['code' => $salah])
        ->assertSessionHasErrors('code');

    $this->get('/reset-password')->assertRedirect(route('password.request'));
});

test('guessing stops after the attempt limit, even with the right code', function () {
    // Inti keamanan kode 6 digit. Tanpa batas ini, sejuta kemungkinan bisa
    // disapu skrip jauh lebih cepat daripada masa berlaku kodenya habis.
    $user = User::factory()->create();
    $code = mintaKode($user);

    for ($i = 0; $i < PasswordResetCode::MAX_ATTEMPTS; $i++) {
        $this->post('/forgot-password/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    // Kode yang benar pun sudah tidak berlaku: tebakan berlebih menghanguskannya.
    $this->post('/forgot-password/verify', ['code' => $code])
        ->assertSessionHasErrors('code');

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

test('an expired code is refused', function () {
    $user = User::factory()->create();
    $code = mintaKode($user);

    $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

    $this->post('/forgot-password/verify', ['code' => $code])
        ->assertSessionHasErrors('code');

    $this->travelBack();
});

test('asking for a new code kills the previous one', function () {
    // Dua kode hidup untuk satu akun berarti dua kali peluang tebakan.
    Notification::fake();

    $user = User::factory()->create();
    $kodeLama = mintaKode($user);

    $this->post('/forgot-password', ['email' => $user->email]);

    $this->post('/forgot-password/verify', ['code' => $kodeLama])
        ->assertSessionHasErrors('code');
});

test('a verified session cannot be pointed at somebody else account', function () {
    // Email diambil dari sesi, bukan dari form. Kalau form bisa menentukannya,
    // kode yang dikirim ke alamat sendiri bisa dipakai mengganti password akun
    // orang lain.
    $penyerang = User::factory()->create();
    $korban = User::factory()->create();
    $passwordKorban = $korban->password;

    $code = mintaKode($penyerang);

    $this->post('/forgot-password/verify', ['code' => $code])
        ->assertRedirect(route('password.reset'));

    $this->post('/reset-password', [
        'email' => $korban->email,
        'password' => 'Str0ng#Pass1',
        'password_confirmation' => 'Str0ng#Pass1',
    ])->assertRedirect(route('login'));

    expect($korban->fresh()->password)->toBe($passwordKorban)
        ->and(Hash::check('Str0ng#Pass1', $penyerang->fresh()->password))->toBeTrue();
});

test('the code session expires instead of lasting forever', function () {
    $user = User::factory()->create();
    $code = mintaKode($user);

    $this->post('/forgot-password/verify', ['code' => $code])
        ->assertRedirect(route('password.reset'));

    // Halaman yang lupa ditutup di komputer bersama tidak boleh selamanya bisa
    // dipakai mengganti password.
    $this->travel(11)->minutes();

    $this->get('/reset-password')->assertRedirect(route('password.request'));

    $this->travelBack();
});

test('a successful reset also unlocks an account locked out by failed logins', function () {
    $user = User::factory()->create([
        'failed_login_attempts' => 5,
        'locked_until' => now()->addMinutes(15),
    ]);

    $code = mintaKode($user);

    $this->post('/forgot-password/verify', ['code' => $code]);
    $this->post('/reset-password', [
        'password' => 'Str0ng#Pass1',
        'password_confirmation' => 'Str0ng#Pass1',
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_until)->toBeNull();
});
