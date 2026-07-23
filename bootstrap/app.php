<?php

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Sanctum SPA: request same-domain (React) terautentikasi via cookie sesi.
        $middleware->statefulApi();

        // Header keamanan di semua response (Phase 19).
        $middleware->append(SecurityHeaders::class);

        // Alias RBAC & keamanan untuk proteksi route.
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Pelanggaran unique constraint (mis. anti double-payment / nomor
        // duplikat akibat balapan) → 409 Conflict yang ramah, bukan 500 yang
        // membocorkan SQL. Hanya untuk request yang mengharapkan JSON (API/SPA).
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->expectsJson() && ($e->getCode() === '23505')) {
                return response()->json([
                    'message' => 'Operasi bentrok dengan data yang sudah ada. Muat ulang lalu coba lagi.',
                ], 409);
            }

            return null;
        });
    })->create();
