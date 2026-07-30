<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the Node/Baileys bridge service (whatsapp-bridge/ at repo root — see
 * .claude/build-plan/11-unofficial-whatsapp.md for why this is a separate process
 * rather than a PHP library: Baileys has no maintained PHP port).
 *
 * The bridge is internal-only, authenticated with a shared API key, never exposed
 * publicly. Laravel's `channels.id` is used as the bridge's instance id directly,
 * so no separate id-mapping table is needed.
 */
class BridgeClient
{
    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.whatsapp_bridge.base_url'))
            ->withHeaders(['X-Api-Key' => config('services.whatsapp_bridge.api_key')])
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * Registers a new instance on the bridge and starts it connecting. Idempotent —
     * calling again for an existing instance id just (re)starts its socket.
     */
    public function createInstance(int $channelId): array
    {
        return $this->http()
            ->post("/instances/{$channelId}")
            ->throw()
            ->json();
    }

    /**
     * Current QR code (base64 PNG data URL) for scanning, if the instance is
     * mid-connection. Returns null once already connected or before a QR has
     * been generated yet.
     */
    public function getQrCode(int $channelId): ?string
    {
        $response = $this->http()->get("/instances/{$channelId}/qr");

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->json('qr');
    }

    public function requestPairingCode(int $channelId, string $phoneNumber): string
    {
        return $this->http()
            ->post("/instances/{$channelId}/pairing-code", ['phone_number' => $phoneNumber])
            ->throw()
            ->json('code');
    }

    public function status(int $channelId): array
    {
        $response = $this->http()->get("/instances/{$channelId}/status");

        if ($response->status() === 404) {
            return ['status' => 'disconnected'];
        }

        return $response->throw()->json();
    }

    /**
     * The linked phone's saved contacts synced in since the bridge instance
     * connected — best-effort convenience for "Import from WhatsApp", not a
     * guaranteed full address-book export (see sessionManager.js).
     */
    public function fetchDeviceContacts(int $channelId): array
    {
        $response = $this->http()->get("/instances/{$channelId}/contacts");

        if ($response->status() === 404) {
            return [];
        }

        return $response->throw()->json('contacts') ?? [];
    }

    /**
     * Public REST API's get_groups — every group the connected instance
     * currently participates in (id/name/participant count only; use
     * fetchGroupParticipants for a specific group's full member list).
     */
    public function listGroups(int $channelId): array
    {
        $response = $this->http()->get("/instances/{$channelId}/groups");

        if ($response->failed()) {
            throw new \RuntimeException($response->json('error') ?? $response->body());
        }

        return $response->json('groups') ?? [];
    }

    /**
     * Which of the given phone numbers are actually registered on WhatsApp
     * (Baileys' onWhatsApp — a lookup, not a message send) — powers the
     * contacts "Validate" action.
     *
     * @return array<int, array{phone: string, exists: bool}>
     */
    public function checkNumbers(int $channelId, array $phones): array
    {
        $response = $this->http()->post("/instances/{$channelId}/check-numbers", ['phones' => $phones]);

        if ($response->failed()) {
            throw new \RuntimeException($response->json('error') ?? $response->body());
        }

        return $response->json('results') ?? [];
    }

    /**
     * On-demand group metadata + participant list (Export Participants,
     * screenshot 90's "Download" action) — requires the instance connected,
     * see sessionManager.js's fetchGroupParticipants.
     *
     * @return array{name: ?string, participants: array<int, array{phone: string, admin: ?string}>}
     */
    public function fetchGroupParticipants(int $channelId, string $groupJid): array
    {
        $response = $this->http()->get("/instances/{$channelId}/groups/".rawurlencode($groupJid).'/participants');

        if ($response->failed()) {
            throw new \RuntimeException($response->json('error') ?? $response->body());
        }

        return $response->json();
    }

    /**
     * Call Responder's "Auto-Reject Incoming Calls" action (screenshot 93) —
     * a real reject stanza, not a hangup of an actual answered call (Baileys
     * never has live audio to hang up in the first place). Callers should
     * treat this as best-effort — a failed reject shouldn't block logging the
     * call or sending whatever text reply still makes sense.
     *
     * Baileys' rejectCall() awaits query(stanza) with no explicit timeout,
     * which means it inherits the fork's defaultQueryTimeoutMs (60s — see
     * whatsapp-bridge/node_modules/@itsliaaa/baileys/lib/Socket/socket.js and
     * Defaults) before it gracefully resolves even if WhatsApp's servers
     * never send back a distinct ack for the reject. The default 15s client
     * timeout here was giving up 45s before the bridge could ever respond —
     * confirmed live via repeated "cURL error 28: timed out after ~15000ms
     * with 0 bytes received". Runs inside RejectCallJob (queued), so a
     * longer wait here just makes that job take longer, not blocking any
     * webhook/HTTP request.
     */
    public function rejectCall(int $channelId, string $callId, string $callFrom): void
    {
        $response = $this->http()->timeout(65)->post("/instances/{$channelId}/calls/{$callId}/reject", ['call_from' => $callFrom]);

        if ($response->failed()) {
            throw new \RuntimeException($response->json('error') ?? $response->body());
        }
    }

    public function logout(int $channelId): void
    {
        try {
            $this->http()->delete("/instances/{$channelId}")->throw();
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                throw $e;
            }
        }
    }

    /**
     * @param  string  $type  'text'|'media'|'poll'|'buttons'|'list'
     * @param  string|null  $mediaType  'image'|'video'|'document'|'audio' — explicit media kind
     *                                  (e.g. from a template's own type) so the bridge doesn't have
     *                                  to guess one from the media_url's file extension.
     * @param  array|null  $interactive  Poll options / buttons / list sections — see
     *                                   WhatsappTemplate::buildInteractiveConfig(). Ignored for text/media.
     */
    public function sendMessage(int $channelId, string $phone, string $type, ?string $body, ?string $mediaUrl = null, ?string $mediaType = null, ?array $interactive = null): array
    {
        // A media send needs Baileys to download the file, encrypt it, and
        // upload it to WhatsApp's servers before the bridge responds — the
        // default 15s timeout (fine for QR/status/etc.) was cutting that off
        // mid-upload, marking the recipient "failed" here even though the
        // bridge went on to actually deliver the message a moment later.
        $response = $this->http()->timeout(90)->post("/instances/{$channelId}/send", [
            'phone' => $phone,
            'type' => $type,
            'body' => $body,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'interactive' => $interactive,
        ]);

        if ($response->failed()) {
            // `->throw()` here would only ever say "HTTP request returned
            // status code 400" — the bridge's own {error: "..."} body (e.g.
            // the actual Baileys failure reason) is what's actually useful
            // to the caller and to whoever's debugging a failed send.
            throw new \RuntimeException($response->json('error') ?? $response->body());
        }

        return $response->json();
    }
}
