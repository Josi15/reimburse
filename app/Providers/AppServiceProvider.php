<?php

namespace App\Providers;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureAuthorization();
        $this->configurePasswordPolicy();
        $this->configureRateLimiting();
        $this->configureAuditLogging();
    }

    /** Catat login & logout ke audit log (Phase 15). */
    private function configureAuditLogging(): void
    {
        Event::listen(Login::class, function (Login $event) {
            app(AuditLogger::class)->log(AuditEvent::Login, userId: $event->user->getAuthIdentifier());
        });

        Event::listen(Logout::class, function (Logout $event) {
            app(AuditLogger::class)->log(AuditEvent::Logout, userId: $event->user?->getAuthIdentifier());
        });
    }

    /**
     * Super Admin melewati semua cek. Ability bergaya permission (mengandung
     * titik, mis. "payment.process") diselesaikan lewat RBAC saat pengecekan —
     * selalu akurat tanpa query per-request dan tanpa masalah staleness.
     * Ability model policy (mis. "view", "update") diteruskan ke policy.
     */
    private function configureAuthorization(): void
    {
        Gate::before(function ($user, string $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }

            if (str_contains($ability, '.')) {
                // null (bukan false) agar tidak mematikan mekanisme lain bila
                // kebetulan ada gate/policy bernama sama.
                return $user->hasPermission($ability) ?: null;
            }

            return null;
        });
    }

    /**
     * Password policy (dipakai otomatis oleh Breeze register/reset/update yang
     * memanggil Password::defaults()).
     */
    private function configurePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->mixedCase()->numbers()->symbols();

            return $this->app->environment('production')
                ? $rule->uncompromised()   // cek kebocoran (HIBP) hanya di produksi
                : $rule;
        });
    }

    /**
     * Rate limiter: login lebih ketat, payment endpoint dibatasi khusus.
     */
    private function configureRateLimiting(): void
    {
        // Login: 5 percobaan/menit per email+IP.
        RateLimiter::for('login', function (Request $request) {
            $key = mb_strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Payment: operasi sensitif → 10 aksi/menit per user.
        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip());
        });

        // API umum.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
