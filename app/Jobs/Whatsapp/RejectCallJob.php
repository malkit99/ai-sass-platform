<?php

namespace App\Jobs\Whatsapp;

use App\Services\Whatsapp\BridgeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Call Responder's "Auto-Reject Incoming Calls" action, on the queue instead
 * of inline inside WebhookController — same reason SendAutoReplyJob already
 * is (see its own docblock): the bridge's 'call' webhook POST has its own
 * 10s timeout, and rejectCall() ultimately waits on a Baileys stanza
 * round-trip that can be slow when the connection is flaky. Calling back
 * into the bridge synchronously from within that same webhook request risks
 * blowing past the bridge's own wait — confirmed live: this exact chain
 * produced repeated "[webhook] failed to deliver \"call.event\": timeout of
 * 10000ms exceeded" before this was moved onto the queue.
 */
class RejectCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $channelId,
        private readonly string $callId,
        private readonly string $callerPhone,
    ) {}

    public function handle(BridgeClient $bridge): void
    {
        try {
            $bridge->rejectCall($this->channelId, $this->callId, $this->callerPhone);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp call auto-reject failed', [
                'channel_id' => $this->channelId,
                'call_id' => $this->callId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
