<?php

namespace App\Models\Concerns;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;

/**
 * Mencatat perubahan model (create/update/delete) ke audit log secara otomatis.
 * Field sensitif & timestamp dikecualikan. Gunakan Model::withoutAuditing(fn)
 * untuk melewati pencatatan otomatis (mis. saat status diubah oleh service yang
 * mencatat event semantik sendiri seperti approve/payment).
 */
trait Auditable
{
    protected static bool $auditingEnabled = true;

    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit(
            AuditEvent::Create, null, $model->auditableAttributes(),
        ));

        static::updated(function ($model) {
            $changes = collect($model->getChanges())->except($model->auditExcluded())->all();
            if (empty($changes)) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAudit(AuditEvent::Update, $old, $changes);
        });

        static::deleted(fn ($model) => $model->writeAudit(
            AuditEvent::Delete, $model->auditableAttributes(), null,
        ));
    }

    /** Jalankan callback tanpa pencatatan audit otomatis. */
    public static function withoutAuditing(callable $callback): mixed
    {
        static::$auditingEnabled = false;

        try {
            return $callback();
        } finally {
            static::$auditingEnabled = true;
        }
    }

    /** Field yang tidak dicatat. Model dapat menambah via $auditExclude. */
    protected function auditExcluded(): array
    {
        return array_merge(
            ['password', 'remember_token', 'failed_login_attempts', 'locked_until', 'created_at', 'updated_at', 'deleted_at'],
            $this->auditExclude ?? [],
        );
    }

    protected function auditableAttributes(): array
    {
        return collect($this->getAttributes())->except($this->auditExcluded())->all();
    }

    protected function writeAudit(AuditEvent $event, ?array $old, ?array $new): void
    {
        if (! static::$auditingEnabled) {
            return;
        }

        app(AuditLogger::class)->log($event, $this, $old, $new);
    }
}
