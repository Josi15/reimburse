<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AuditLog — jejak audit generik (Phase 15). APPEND-ONLY:
 * hanya created_at (updated_at dinonaktifkan), tanpa soft delete.
 */
class AuditLog extends Model
{
    /** Tabel append-only: kelola created_at saja. */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Filter berdasarkan jenis event. */
    public function scopeEvent(Builder $query, AuditEvent|string $event): Builder
    {
        return $query->where('event', $event instanceof AuditEvent ? $event->value : $event);
    }
}
