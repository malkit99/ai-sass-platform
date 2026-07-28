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
     * @param  string  $type  'text'|'media'
     * @param  string|null  $mediaType  'image'|'video'|'document'|'audio' — explicit media kind
     *                                  (e.g. from a template's own type) so the bridge doesn't have
     *                                  to guess one from the media_url's file extension.
     */
    public function sendMessage(int $channelId, string $phone, string $type, ?string $body, ?string $mediaUrl = null, ?string $mediaType = null): array
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
