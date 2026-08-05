<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Notifications\Dispatcher;
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
