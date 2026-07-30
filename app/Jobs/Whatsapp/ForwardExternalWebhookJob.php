<?php

namespace App\Jobs\Whatsapp;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Public REST API's set_webhook (screenshot 38's "Set Webhook") — forwards
 * connection-status and inbound-message events to an external caller's own
 * URL, HMAC-signed with the channel's access_token as secret (mirrors
 * WebhookController's own bridge->Laravel signature scheme, just with the
 * per-channel token instead of the shared bridge secret). Queued rather than
 * called inline from WebhookController for the same reason RejectCallJob and
 * SendAutoReplyJob already are — an unreachable/slow third-party URL must
 * never risk blowing past the bridge's own webhook-delivery timeout.
 */
class ForwardExternalWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $url,
        private readonly string $accessToken,
        private readonly array $payload,
    ) {}

    public function handle(): void
    {
        $body = json_encode($this->payload);
        $signature = hash_hmac('sha256', $body, $this->accessToken);

        try {
            Http::withBody($body, 'application/json')
                ->withHeaders(['X-Webhook-Signature' => $signature])
                ->timeout(15)
                ->post($this->url)
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('External WhatsApp webhook delivery failed', [
                'url' => $this->url,
                'event' => $this->payload['event'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
