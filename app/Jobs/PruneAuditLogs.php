<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PruneAuditLogs implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $retentionDays = (int) config('app.audit_retention_days', 90);

        $deleted = AuditLog::where('created_at', '<', now()->subDays($retentionDays))->delete();

        logger()->info("Pruned {$deleted} audit logs older than {$retentionDays} days.");
    }
}
