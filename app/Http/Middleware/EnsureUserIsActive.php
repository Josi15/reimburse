<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blokir user yang dinonaktifkan atau sedang terkunci meski sesinya masih aktif.
 * Logout paksa lalu arahkan ke login.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || ($user->locked_until && $user->locked_until->isFuture()))) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Akun Anda tidak aktif atau sedang terkunci. Hubungi administrator.');
        }

        return $next($request);
    }
}
