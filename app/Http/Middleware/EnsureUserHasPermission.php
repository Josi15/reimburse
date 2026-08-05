<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses route berdasarkan permission. Penggunaan:
 * ->middleware('permission:payment.process').
 *
 * Beberapa permission dipisah koma berarti ANY-OF — cukup punya salah satunya:
 * ->middleware('permission:reimbursement.view,reimbursement.viewAny').
 * Sengaja any-of (bukan all-of) agar sama dengan aturan menu sidebar di
 * App\Support\Navigation; kalau kedua tempat berbeda arti, menu bisa tampil
 * tapi halamannya menolak.
 *
 * Super Admin selalu diizinkan.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Anda tidak memiliki izin untuk melakukan aksi ini.');
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'Anda tidak memiliki izin untuk melakukan aksi ini.');
    }
}
