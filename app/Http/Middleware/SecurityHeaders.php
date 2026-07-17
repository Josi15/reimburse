<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar (Phase 19):
 * - nosniff     : cegah browser menebak content-type (mitigasi XSS via upload).
 * - DENY frame  : cegah clickjacking.
 * - referrer    : jangan bocorkan URL internal ke situs eksternal.
 * - permissions : matikan API browser yang tidak dipakai.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
