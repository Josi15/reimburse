<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses route berdasarkan role. Penggunaan: ->middleware('role:admin,finance').
 * Super Admin selalu diizinkan.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('super_admin') && ! $user->hasRole(...$roles))) {
            abort(403, 'Anda tidak memiliki role yang dibutuhkan untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
