<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Keeps the audit trail useful without letting it grow unbounded — entries
 * older than the retention window are just history noise at that point, not
 * anything anyone still needs, and an ever-growing table is a real operational
 * cost across every tenant on a multi-tenant platform like this one.
 */
class PruneActivityLogs extends Command
{
    protected $signature = 'app:prune-activity-logs {--days=90 : Delete entries older than this many days}';

    protected $description = 'Delete activity log entries older than the retention window';

    public function handle(): void
    {
        $days = (int) $this->option('days');

        // No authenticated user in a scheduled/CLI run, so BelongsToTenant's
        // global scope is a no-op here — this intentionally prunes across
        // every tenant in one pass, not just one account.
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} activity log ".Str::plural('entry', $deleted)." older than {$days} days.");
    }
}
