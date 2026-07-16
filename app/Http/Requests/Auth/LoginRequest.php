<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    /** Jumlah kegagalan sebelum akun dikunci sementara. */
    private const MAX_FAILED_ATTEMPTS = 5;

    /** Durasi kunci akun (menit). */
    private const LOCK_MINUTES = 15;

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->string('email'))->first();

        // Akun terkunci (lockout berbasis DB, bertahan lintas IP).
        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds = now()->diffInSeconds($user->locked_until, false),
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        // Akun dinonaktifkan admin.
        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda tidak aktif. Silakan hubungi administrator.',
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            $this->registerFailedAttempt($user);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Sukses: bersihkan penghitung.
        RateLimiter::clear($this->throttleKey());
        $user?->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
    }

    /** Naikkan penghitung gagal; kunci akun bila melampaui ambang. */
    private function registerFailedAttempt(?User $user): void
    {
        if (! $user) {
            return;
        }

        $attempts = $user->failed_login_attempts + 1;

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => now()->addMinutes(self::LOCK_MINUTES),
            ])->save();
        } else {
            $user->forceFill(['failed_login_attempts' => $attempts])->save();
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
