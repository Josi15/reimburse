<?php

namespace App\Providers;

use App\Enums\AuditEvent;
use App\Models\Reimbursement;
use App\Models\User;
use App\Services\AuditLogger;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
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
        $this->configurePasswordReset();
        $this->configureApiDocs();
    }

    /** Dokumentasi API (Scramble/OpenAPI) — Phase 18. */
    private function configureApiDocs(): void
    {
        // Akses /docs/api: bebas di local/testing; hanya Super Admin di env lain.
        Gate::define('viewApiDocs', function ($user = null) {
            return $this->app->environment(['local', 'testing'])
                || ($user?->hasRole('super_admin') ?? false);
        });

        // Deklarasikan skema auth Bearer (Sanctum token) pada spesifikasi.
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
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
     * Setelah password berhasil di-reset, buka kunci akunnya.
     *
     * Lockout (5 kali gagal login) justru paling sering menimpa orang yang lupa
     * password. Tanpa ini, ia sudah menyetel password baru tapi tetap ditolak
     * sampai masa kunci habis — reset jadi terasa tidak berfungsi.
     */
    private function configurePasswordReset(): void
    {
        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            $user = $event->user;

            if (! $user instanceof User) {
                return;
            }

            User::withoutAuditing(fn () => $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save());

            app(AuditLogger::class)->log(
                AuditEvent::Update,
                $user,
                description: 'Password direset lewat tautan email',
                userId: $user->id,
            );
        });
    }

    /**
     * Ability yang TIDAK boleh dilewati Super Admin: keputusan atas uang.
     * Pemisahan tugas berlaku untuk semua orang — kalau Super Admin bisa
     * menyetujui lalu mencairkan klaimnya sendiri, seluruh rantai kontrol
     * runtuh pada satu akun. Ability ini diteruskan ke policy, yang tetap
     * mengizinkan Super Admin memutuskan klaim ORANG LAIN.
     */
    private const SEPARATION_OF_DUTIES_ABILITIES = [
        'approveManager',
        'approveFinance',
        'approveDirector',
    ];

    /**
     * Super Admin melewati semua cek, KECUALI ability pemisahan tugas di atas.
     * Ability bergaya permission (mengandung titik, mis. "payment.process")
     * diselesaikan lewat RBAC saat pengecekan — selalu akurat tanpa query
     * per-request dan tanpa masalah staleness. Ability model policy (mis.
     * "view", "update") diteruskan ke policy.
     */
    private function configureAuthorization(): void
    {
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if ($user->hasRole('super_admin')) {
                return $this->bypassesSeparationOfDuties($ability, $arguments) ? null : true;
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
     * Benarkah ability ini menyangkut pemisahan tugas (sehingga bypass Super
     * Admin harus dinonaktifkan dan keputusannya diserahkan ke policy)?
     *
     * `create` sengaja diperiksa lewat argumennya: PaymentPolicy::create
     * menyertakan Reimbursement (pencairan dana — dijaga), sedangkan
     * ReimbursementPolicy::create tidak menyertakan apa pun (membuat pengajuan
     * — tidak dijaga). Seluruh argumen dipindai, bukan hanya yang pertama,
     * karena pemanggilnya memakai bentuk authorize('create', [Payment::class,
     * $reimbursement]).
     */
    private function bypassesSeparationOfDuties(string $ability, array $arguments): bool
    {
        if (in_array($ability, self::SEPARATION_OF_DUTIES_ABILITIES, true)) {
            return true;
        }

        if ($ability !== 'create') {
            return false;
        }

        foreach ($arguments as $argument) {
            if ($argument instanceof Reimbursement) {
                return true;
            }
        }

        return false;
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

        // Lupa password: 6 permintaan/menit per IP. Broker Laravel sudah
        // membatasi per akun (1 tautan/60 detik); ini menutup celah satu IP
        // yang mencoba banyak alamat email sekaligus.
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(6)->by($request->ip());
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
