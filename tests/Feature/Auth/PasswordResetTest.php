<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('an unknown email gets the same neutral answer, without an error', function () {
    // Jawaban dibuat identik supaya halaman ini tidak bisa dipakai memeriksa
    // email mana yang punya akun di sistem.
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'bukan-pengguna@fundback.test'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

test('a dead SMTP server does not reveal that an email is registered', function () {
    // Kredensial SMTP yang salah membuat pengiriman melempar exception, dan itu
    // HANYA terjadi kalau emailnya terdaftar — email asing berhenti lebih awal
    // tanpa pernah menyentuh mailer. Kalau exception-nya dibiarkan naik jadi
    // HTTP 500, selisih 500-vs-302 itu sendiri sudah cukup untuk memetakan
    // seluruh daftar pengguna tanpa login.
    $user = User::factory()->create();

    $this->mock(Dispatcher::class)
        ->shouldReceive('send')
        ->andThrow(new TransportException('SMTP auth failed'));

    $terdaftar = $this->post('/forgot-password', ['email' => $user->email]);
    $statusTerdaftar = session('status');

    $asing = $this->post('/forgot-password', ['email' => 'bukan-pengguna@fundback.test']);

    $terdaftar->assertSessionHasNoErrors();
    $asing->assertSessionHasNoErrors();

    // Keduanya harus tidak bisa dibedakan: status code maupun teksnya.
    expect($terdaftar->getStatusCode())->toBe($asing->getStatusCode())
        ->and($statusTerdaftar)->toBe(session('status'))
        ->and($statusTerdaftar)->not->toBeNull();
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Str0ng#Pass1',
            'password_confirmation' => 'Str0ng#Pass1',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('the reset link really dies after its 2 minute lifetime', function () {
    // Menguji penolakan yang sebenarnya, bukan sekadar angka di teks email:
    // tautan yang lewat masa berlaku harus ditolak broker, bukan cuma terlihat
    // kedaluwarsa.
    Notification::fake();

    $user = User::factory()->create();
    $passwordLama = $user->password;

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        // Masih hidup di menit pertama.
        $this->travel(1)->minutes();
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Str0ng#Pass1',
            'password_confirmation' => 'Str0ng#Pass1',
        ])->assertSessionHasNoErrors();

        // Tautan baru, lalu lewatkan masa berlakunya.
        $this->post('/forgot-password', ['email' => $user->email]);
        $this->travel(3)->minutes();

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Laen#Pass987',
            'password_confirmation' => 'Laen#Pass987',
        ])->assertSessionHasErrors('email');

        $this->travelBack();

        return true;
    });

    // Password tetap berubah oleh percobaan pertama, tapi tidak oleh yang kedua.
    expect($user->fresh()->password)->not->toBe($passwordLama)
        ->and(Hash::check('Laen#Pass987', $user->fresh()->password))->toBeFalse();
});

test('the request page announces the real lifetime, not a hardcoded one', function () {
    // Halamannya dulu menulis "60 menit" sebagai teks biasa dan tetap begitu
    // setelah confignya dipendekkan — janji yang meleset bikin pengguna
    // menyalahkan sistem saat tautannya mati lebih cepat dari yang tertulis.
    $this->get('/forgot-password')
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ForgotPassword')
            ->where('expireMinutes', config('auth.passwords.users.expire'))
        );
});

test('the reset email states the real lifetime, not a hardcoded one', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $isi = $notification->toMail($user)->render()->toHtml();

        expect($isi)->toContain('berlaku '.config('auth.passwords.users.expire').' menit');

        return true;
    });
});

test('a successful reset also unlocks an account locked out by failed logins', function () {
    Notification::fake();

    $user = User::factory()->create([
        'failed_login_attempts' => 5,
        'locked_until' => now()->addMinutes(15),
    ]);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Str0ng#Pass1',
            'password_confirmation' => 'Str0ng#Pass1',
        ])->assertSessionHasNoErrors();

        return true;
    });

    $user->refresh();

    expect($user->failed_login_attempts)->toBe(0)
        ->and($user->locked_until)->toBeNull();
});
