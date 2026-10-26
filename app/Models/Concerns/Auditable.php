<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records created / updated / deleted events (with changed values) to audit_logs.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLogger::log('created', $model, class_basename($model).' created', [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            if (! $changes) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            AuditLogger::log('updated', $model, class_basename($model).' updated', $old, $changes);
        });

        static::deleted(function (Model $model) {
            AuditLogger::log('deleted', $model, class_basename($model).' deleted', $model->getAttributes());
        });
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }
}
