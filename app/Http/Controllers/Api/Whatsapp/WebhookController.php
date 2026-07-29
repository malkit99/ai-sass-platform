<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Jobs\Whatsapp\SendAutoReplyJob;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAutoresponder;
use App\Models\WhatsappChatbotRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives events pushed by the Node/Baileys bridge (whatsapp-bridge/) — connection
 * status changes and inbound messages. Not behind auth:sanctum (the bridge is a
 * server-to-server caller, not a browser session); authenticated instead via an
 * HMAC signature over the raw body, see verifySignature().
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! $this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $event = $request->input('event');
        $channel = Channel::withoutGlobalScopes()->find($request->input('channel_id'));

        if (! $channel) {
            return response()->json(['error' => 'Unknown channel'], Response::HTTP_NOT_FOUND);
        }

        match ($event) {
            'connection.update' => $this->handleConnectionUpdate($channel, $request),
            'message.inbound' => $this->handleInboundMessage($channel, $request),
            default => Log::warning('Unhandled WhatsApp bridge webhook event', ['event' => $event]),
        };

        return response()->json(['ok' => true]);
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Bridge-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), config('services.whatsapp_bridge.webhook_secret'));

        return hash_equals($expected, $signature);
    }

    private function handleConnectionUpdate(Channel $channel, Request $request): void
    {
        $status = $request->input('status');

        $channel->update([
            'status' => $status,
            'connected_at' => $status === Channel::STATUS_CONNECTED ? now() : $channel->connected_at,
            // Only sent by the bridge on the 'connected' event — leaves any
            // previously-known profile info untouched on disconnect/reconnect.
            'profile_name' => $request->input('profile_name') ?? $channel->profile_name,
            'profile_phone' => $request->input('profile_phone') ?? $channel->profile_phone,
        ]);
    }

    private function handleInboundMessage(Channel $channel, Request $request): void
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'name' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'media_url' => ['nullable', 'string'],
            'external_id' => ['nullable', 'string'],
        ]);

        $conversation = Conversation::withoutGlobalScopes()->firstOrCreate(
            ['channel_id' => $channel->id, 'contact_phone' => $data['phone']],
            ['account_id' => $channel->account_id, 'contact_name' => $data['name'] ?? null, 'last_message_at' => now()]
        );

        $conversation->messages()->create([
            'direction' => Message::DIRECTION_IN,
            'type' => $data['type'] ?? 'text',
            'body' => $data['body'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'status' => 'received',
            'external_id' => $data['external_id'] ?? null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $this->maybeAutoReply($channel, $conversation, $data['body'] ?? null);
    }

    /**
     * Chatbot keyword rules take priority over the plain autoresponder — a
     * chatbot rule is an explicit "if they said X" match, the autoresponder is
     * the unconditional fallback (mirrors the reference app's Autoresponder vs.
     * Chatbot distinction, screenshots 34/35). Channel-specific rules are tried
     * before account-wide (`channel_id === null`) ones.
     */
    private function maybeAutoReply(Channel $channel, Conversation $conversation, ?string $inboundBody): void
    {
        $rule = WhatsappChatbotRule::withoutGlobalScopes()
            ->where('account_id', $channel->account_id)
            ->where(fn ($q) => $q->where('channel_id', $channel->id)->orWhereNull('channel_id'))
            ->where('enabled', true)
            ->orderByRaw('channel_id IS NULL') // channel-specific (0) before account-wide (1)
            ->get()
            ->first(fn (WhatsappChatbotRule $rule) => $rule->matches($inboundBody));

        if ($rule) {
            SendAutoReplyJob::dispatch($channel->id, $conversation->id, $rule->message_type, $rule->body, $rule->media_url, null);

            return;
        }

        // Channel-specific rules before account-wide ones; appliesTo() covers
        // except-contacts and the all/individual/group target scope (screenshot
        // 77's "Sent to").
        $autoresponder = WhatsappAutoresponder::withoutGlobalScopes()
            ->where('account_id', $channel->account_id)
            ->where(fn ($q) => $q->where('channel_id', $channel->id)->orWhereNull('channel_id'))
            ->where('enabled', true)
            ->orderByRaw('channel_id IS NULL')
            ->get()
            ->first(fn (WhatsappAutoresponder $rule) => $rule->appliesTo($conversation->contact_phone));

        if (! $autoresponder) {
            return;
        }

        // "Resubmit message only after (minute)" (screenshot 78) — a cooldown
        // per conversation, not per rule, since re-triggering seconds after
        // the last auto-reply is exactly the spammy behavior this exists to
        // prevent regardless of which rule fired last time.
        $lastAutoReply = $conversation->messages()
            ->where('sent_by', 'auto_reply')
            ->latest('created_at')
            ->first();

        if ($lastAutoReply && $lastAutoReply->created_at->diffInMinutes(now()) < $autoresponder->resubmit_after_minutes) {
            return;
        }

        SendAutoReplyJob::dispatch(
            $channel->id, $conversation->id, $autoresponder->message_type, $autoresponder->body,
            $autoresponder->media_url, $autoresponder->interactive_config,
        );
    }
}
