<?php

namespace App\Console\Commands;

use App\Models\WhatsappGroup;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Same reasoning as PruneActivityLogs — groups discovered on a channel that's
 * gone idle/disconnected would otherwise sit forever. Uses last_synced_at
 * (when its participant list was actually last downloaded) rather than
 * created_at when available — a group discovered a year ago but exported
 * last week is still current and shouldn't be pruned just because the row is
 * old; only groups nobody has touched in the retention window are stale.
 * whatsapp_group_participants doesn't need its own prune pass — it cascade-
 * deletes with its parent group (see the migration's FK).
 */
class PruneWhatsappGroups extends Command
{
    protected $signature = 'app:prune-whatsapp-groups {--days=90 : Delete groups not synced (or, if never synced, not discovered) in this many days}';

    protected $description = 'Delete WhatsApp groups (and their cached participants) inactive past the retention window';

    public function handle(): void
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        // No authenticated user in a scheduled/CLI run, so BelongsToTenant's
        // global scope is a no-op here — this intentionally prunes across
        // every tenant in one pass, same as PruneActivityLogs.
        $deleted = WhatsappGroup::where(function ($query) use ($cutoff) {
            $query->where('last_synced_at', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('last_synced_at')->where('created_at', '<', $cutoff));
        })->delete();

        $this->info("Deleted {$deleted} WhatsApp ".Str::plural('group', $deleted)." (and their cached participants) inactive past {$days} days.");
    }
}
