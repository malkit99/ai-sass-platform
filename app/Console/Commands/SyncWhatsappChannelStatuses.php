<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\Whatsapp\BridgeClient;
use Illuminate\Console\Command;

/**
 * Reconciles `channels.status` against the bridge's actual live connection
 * state. The DB column is only ever updated by webhook events pushed *from*
 * the bridge on a graceful connect/disconnect — a bridge crash or restart
 * never fires one, so a channel can sit marked "connected" long after the
 * bridge itself lost that session. This catches that drift on a schedule
 * instead of only at the moment someone tries to send and hits a confusing
 * "instance not connected" error (see MessageController::store(), which does
 * the same live check inline for the immediate case).
 */
class SyncWhatsappChannelStatuses extends Command
{
    protected $signature = 'app:sync-whatsapp-channel-statuses';

    protected $description = "Reconcile channels.status against the bridge's live connection state";

    public function handle(BridgeClient $bridge): void
    {
        // No authenticated user in a scheduled/CLI run, so BelongsToTenant's
        // global scope is a no-op here — intentionally checks every tenant's
        // channels in one pass.
        $channels = Channel::whereIn('status', [Channel::STATUS_CONNECTED, Channel::STATUS_CONNECTING])->get();
        $driftCount = 0;

        foreach ($channels as $channel) {
            $liveStatus = $bridge->status($channel->id)['status'] ?? Channel::STATUS_DISCONNECTED;

            if ($liveStatus === $channel->status) {
                continue;
            }

            $driftCount++;
            $this->info("Channel {$channel->id}: {$channel->status} -> {$liveStatus}");

            $channel->update([
                'status' => $liveStatus,
                'connected_at' => $liveStatus === Channel::STATUS_CONNECTED ? $channel->connected_at : null,
            ]);
        }

        $this->info("Checked {$channels->count()} channel(s), {$driftCount} drifted.");
    }
}
