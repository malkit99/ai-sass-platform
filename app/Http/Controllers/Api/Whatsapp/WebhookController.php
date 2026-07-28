<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAutoresponder;
use App\Models\WhatsappChatbotRule;
use App\Models\WhatsappCreditBalance;
use App\Models\WhatsappCreditLedger;
use App\Services\Whatsapp\BridgeClient;
use App\Services\Whatsapp\Spintax;
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
    public function __construct(private readonly BridgeClient $bridge) {}

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
            $this->sendAutoReply($channel, $conversation, $rule->message_type, $rule->body, $rule->media_url);

            return;
        }

        $autoresponder = WhatsappAutoresponder::withoutGlobalScopes()
            ->where('account_id', $channel->account_id)
            ->where(fn ($q) => $q->where('channel_id', $channel->id)->orWhereNull('channel_id'))
            ->where('enabled', true)
            ->orderByRaw('channel_id IS NULL')
            ->first();

        if ($autoresponder) {
            $this->sendAutoReply($channel, $conversation, $autoresponder->message_type, $autoresponder->body, $autoresponder->media_url);
        }
    }

    private function sendAutoReply(Channel $channel, Conversation $conversation, string $type, ?string $body, ?string $mediaUrl): void
    {
        $account = Account::withoutGlobalScopes()->find($channel->account_id);
        $balance = WhatsappCreditBalance::forAccount($account);

        if ($balance->credits_remaining < 1) {
            Log::info('Skipped WhatsApp auto-reply — no credits remaining', ['channel_id' => $channel->id]);

            return;
        }

        $body = Spintax::render($body);

        try {
            $this->bridge->sendMessage($channel->id, $conversation->contact_phone, $type, $body, $mediaUrl);

            $conversation->messages()->create([
                'direction' => Message::DIRECTION_OUT,
                'type' => $type,
                'body' => $body,
                'media_url' => $mediaUrl,
                'status' => 'sent',
                'sent_by' => 'auto_reply',
            ]);

            $conversation->update(['last_message_at' => now()]);

            WhatsappCreditLedger::record($account, -1, WhatsappCreditLedger::REASON_MESSAGE_SENT);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp auto-reply failed to send', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);
        }
    }
}
