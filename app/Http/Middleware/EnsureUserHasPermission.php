<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses route berdasarkan permission. Penggunaan:
 * ->middleware('permission:payment.process'). Super Admin selalu diizinkan.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('super_admin') && ! $user->hasPermission($permission))) {
            abort(403, 'Anda tidak memiliki izin untuk melakukan aksi ini.');
        }

        return $next($request);
    }
}
