<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Penulis jejak audit terpusat. Menangkap user, waktu, IP, browser, URL,
 * serta old/new data. Dipakai oleh Auditable trait (CRUD), listener auth
 * (login/logout), dan service domain (approve/reject/payment).
 */
class AuditLogger
{
    public function log(
        AuditEvent $event,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
        ?int $userId = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'url' => $request ? substr((string) $request->fullUrl(), 0, 500) : null,
        ]);
    }
}
