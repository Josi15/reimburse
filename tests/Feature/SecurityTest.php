<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// ---- Password policy ------------------------------------------------------

test('registration rejects weak passwords', function () {
    $this->post('/register', [
        'name' => 'Weak User',
        'email' => 'weak@example.com',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

test('registration accepts a strong password', function () {
    $this->post('/register', [
        'name' => 'Strong User',
        'email' => 'strong@example.com',
        'password' => 'Str0ng#Pass1',
        'password_confirmation' => 'Str0ng#Pass1',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

// ---- Account lockout ------------------------------------------------------

test('account locks after five failed login attempts', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    expect($user->fresh()->locked_until)->not->toBeNull();

    // Meski password benar, login ditolak selama terkunci.
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertGuest();
});

// ---- Inactive account -----------------------------------------------------

test('inactive user cannot log in even with correct password', function () {
    $user = User::factory()->inactive()->create(['password' => Hash::make('password')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('active user with correct password logs in', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});
