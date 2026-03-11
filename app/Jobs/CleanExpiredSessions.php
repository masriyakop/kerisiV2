<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CleanExpiredSessions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $deleted = DB::table('sessions')
            ->where('last_activity', '<', now()->subHours(24)->timestamp)
            ->delete();

        logger()->info("Cleaned {$deleted} expired sessions.");
    }
}
