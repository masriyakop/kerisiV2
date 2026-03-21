<?php

namespace App\Http\Traits;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            self::logAudit('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $oldValues = $model->getOriginal();
            $newValues = $model->getChanges();

            // Filter out sensitive fields
            $sensitiveFields = ['password', 'remember_token'];
            $oldValues = array_diff_key($oldValues, array_flip($sensitiveFields));
            $newValues = array_diff_key($newValues, array_flip($sensitiveFields));

            if (! empty($newValues)) {
                // Only include old values for changed fields
                $relevantOld = array_intersect_key($oldValues, $newValues);
                self::logAudit('updated', $model, $relevantOld, $newValues);
            }
        });

        static::deleted(function ($model) {
            self::logAudit('deleted', $model, $model->getAttributes(), null);
        });
    }

    private static function logAudit(string $action, $model, ?array $oldValues, ?array $newValues): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Don't let audit logging failures break the app
            logger()->error('Audit log failed: '.$e->getMessage());
        }
    }
}
