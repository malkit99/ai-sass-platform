<?php

namespace App\Console\Commands;

use App\Models\WhatsappCallLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Same reasoning as PruneActivityLogs — call history for a channel that's
 * gone idle/disconnected (not deleted, so no cascade to rely on) would
 * otherwise grow unbounded forever across every tenant.
 */
class PruneWhatsappCallLogs extends Command
{
    protected $signature = 'app:prune-whatsapp-call-logs {--days=90 : Delete entries older than this many days}';

    protected $description = 'Delete WhatsApp call responder history older than the retention window';

    public function handle(): void
    {
        $days = (int) $this->option('days');

        // No authenticated user in a scheduled/CLI run, so BelongsToTenant's
        // global scope is a no-op here — this intentionally prunes across
        // every tenant in one pass, same as PruneActivityLogs.
        $deleted = WhatsappCallLog::where('started_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} WhatsApp call log ".Str::plural('entry', $deleted)." older than {$days} days.");
    }
}
