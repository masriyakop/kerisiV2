<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit event.
     *
     * @param  string  $action  The action performed (e.g., 'created', 'updated', 'deleted').
     * @param  mixed  $user  The user performing the action (model instance or ID).
     * @param  string|null  $auditableType  The type of the auditable entity (e.g., 'Post', 'Page').
     * @param  mixed  $auditableId  The ID of the auditable entity.
     * @param  array|null  $oldValues  The previous values before the change.
     * @param  array|null  $newValues  The new values after the change.
     */
    public function log(
        string $action,
        $user,
        ?string $auditableType = null,
        $auditableId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $userId = is_object($user) ? $user->id : $user;

        DB::table('audit_logs')->insert([
            'action' => $action,
            'user_id' => $userId,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Log an authentication event (login/logout).
     *
     * @param  string  $action  The auth action (e.g., 'login', 'logout').
     * @param  mixed  $user  The user performing the action.
     */
    public function logAuth(string $action, $user): void
    {
        $this->log($action, $user);
    }
}
