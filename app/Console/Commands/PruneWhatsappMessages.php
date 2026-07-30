<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Same reasoning as PruneActivityLogs/PruneWhatsappCallLogs — the message
 * log (Message History, screenshots-free feature built 2026-07-30) would
 * otherwise grow unbounded forever across every tenant. Prunes individual
 * `messages` rows only, not their parent `conversations` — a conversation is
 * lightweight contact metadata (phone/name/lead_id), not log data, and stays
 * around so the CRM/lead linkage survives even once its old messages are
 * gone (mirrors PruneWhatsappCallLogs treating the log row itself as the
 * discardable unit, rather than PruneWhatsappGroups' cascade-the-container
 * pattern, which only applies because a stale WhatsappGroup itself is the
 * discardable unit).
 */
class PruneWhatsappMessages extends Command
{
    protected $signature = 'app:prune-whatsapp-messages {--days=90 : Delete messages older than this many days}';

    protected $description = 'Delete WhatsApp message history older than the retention window';

    public function handle(): void
    {
        $days = (int) $this->option('days');

        // No authenticated user in a scheduled/CLI run, so BelongsToTenant's
        // global scope is a no-op here — this intentionally prunes across
        // every tenant in one pass, same as PruneActivityLogs. Message has
        // no BelongsToTenant of its own (scoped only via its conversation),
        // so this is a plain unscoped delete.
        $deleted = Message::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} WhatsApp ".Str::plural('message', $deleted)." older than {$days} days.");
    }
}
