<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Jobs\Whatsapp\RejectCallJob;
use App\Jobs\Whatsapp\SendAutoReplyJob;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappAutoresponder;
use App\Models\WhatsappCallLog;
use App\Models\WhatsappCallResponderSetting;
use App\Models\WhatsappChatbotRule;
use App\Models\WhatsappGroup;
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
            'group.seen' => $this->handleGroupSeen($channel, $request),
            'call.event' => $this->handleCallEvent($channel, $request),
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
     * Passive group discovery (Export Participants, screenshot 90's "send a
     * message to the group" step) — just records that this group exists.
     * name/participant_count/last_synced_at are only populated by an actual
     * export, which calls the bridge's on-demand groupMetadata fetch.
     */
    private function handleGroupSeen(Channel $channel, Request $request): void
    {
        $data = $request->validate(['group_jid' => ['required', 'string']]);

        WhatsappGroup::withoutGlobalScopes()->firstOrCreate(
            ['channel_id' => $channel->id, 'group_jid' => $data['group_jid']],
            ['account_id' => $channel->account_id],
        );
    }

    /**
     * Call Responder (screenshots 93/94) — Baileys only ever sees call
     * signaling (offer/accept/reject/timeout/terminate), never actual audio,
     * so this can only auto-reject + send a text reply. `terminate` is a
     * generic "call ended" status (per the bridge fork's own code comment:
     * "fired when accepted/rejected/timeout/caller hangs up") — telling apart
     * "you declined on your phone" vs "call was answered then ended" vs
     * "caller cancelled" is done here by tracking each call_id's own state
     * across its offer→...→terminal sequence, not by reading one field.
     */
    private function handleCallEvent(Channel $channel, Request $request): void
    {
        $data = $request->validate([
            'call_id' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'is_video' => ['boolean'],
            'status' => ['required', 'string'],
            'auto_rejected' => ['boolean'],
        ]);

        $log = WhatsappCallLog::withoutGlobalScopes()->firstOrCreate(
            ['channel_id' => $channel->id, 'call_id' => $data['call_id']],
            [
                'account_id' => $channel->account_id,
                'caller_phone' => $data['phone'],
                'is_video' => $data['is_video'] ?? false,
                'status' => WhatsappCallLog::STATUS_RINGING,
                'started_at' => now(),
            ],
        );

        if ($data['status'] === 'offer') {
            $this->maybeRespondToOffer($channel, $log);

            return;
        }

        if ($data['status'] === 'accept') {
            $log->update(['status' => WhatsappCallLog::STATUS_ANSWERED]);

            return;
        }

        if (! in_array($data['status'], ['reject', 'timeout', 'terminate'], true)) {
            return; // e.g. 'relaylatency' — not something Call Responder acts on
        }

        if ($data['auto_rejected'] ?? false) {
            // Reply was already dispatched at offer time — nothing more to send.
            $log->update(['status' => WhatsappCallLog::STATUS_AUTO_REJECTED, 'ended_at' => now()]);

            return;
        }

        $setting = WhatsappCallResponderSetting::withoutGlobalScopes()
            ->where('channel_id', $channel->id)
            ->where('enabled', true)
            ->first();

        if ($log->status === WhatsappCallLog::STATUS_ANSWERED) {
            $this->finishCall($channel, $log, $setting, WhatsappCallLog::STATUS_COMPLETED, 'after_call_reply');
        } elseif ($data['status'] === 'timeout') {
            $this->finishCall($channel, $log, $setting, WhatsappCallLog::STATUS_MISSED, 'missed_before_answer_reply');
        } else {
            // A 'reject'/generic 'terminate' we didn't cause and never saw
            // 'accept' for — the phone itself declined it.
            $this->finishCall($channel, $log, $setting, WhatsappCallLog::STATUS_MANUALLY_REJECTED, 'rejected_call_reply');
        }
    }

    private function maybeRespondToOffer(Channel $channel, WhatsappCallLog $log): void
    {
        $setting = WhatsappCallResponderSetting::withoutGlobalScopes()
            ->where('channel_id', $channel->id)
            ->where('enabled', true)
            ->first();

        if (! $setting) {
            return;
        }

        if ($setting->auto_reject_enabled) {
            // Queued, not called inline — this webhook request is the bridge
            // waiting on its own 10s timeout, and rejectCall() ultimately
            // waits on a Baileys stanza round-trip that can be slow. See
            // RejectCallJob's docblock — this is the same reason
            // SendAutoReplyJob is already queued rather than inline.
            RejectCallJob::dispatch($channel->id, $log->call_id, $log->caller_phone);

            $this->sendCallReply($channel, $log, $setting->missed_call_reply, $setting->reply_delay_seconds, 'missed_call_reply');
        }
    }

    private function finishCall(Channel $channel, WhatsappCallLog $log, ?WhatsappCallResponderSetting $setting, string $status, string $replyField): void
    {
        $log->update(['status' => $status, 'ended_at' => now()]);

        if (! $setting) {
            return;
        }

        $this->sendCallReply($channel, $log, $setting->{$replyField}, $setting->reply_delay_seconds, $replyField);
    }

    private function sendCallReply(Channel $channel, WhatsappCallLog $log, ?string $body, int $delaySeconds, string $replyType): void
    {
        if (! $body) {
            return;
        }

        $conversation = Conversation::withoutGlobalScopes()->firstOrCreate(
            ['channel_id' => $channel->id, 'contact_phone' => $log->caller_phone],
            ['account_id' => $channel->account_id, 'last_message_at' => now()],
        );

        SendAutoReplyJob::dispatch(
            $channel->id, $conversation->id, 'text', $body, null, null, "call_responder:{$channel->id}",
        )->delay(now()->addSeconds($delaySeconds));

        $log->update(['reply_type' => $replyType]);
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
            ->first(fn (WhatsappChatbotRule $rule) => $rule->matches($inboundBody, $conversation->contact_phone));

        if ($rule) {
            SendAutoReplyJob::dispatch(
                $channel->id, $conversation->id, $rule->message_type, $rule->body,
                $rule->media_url, $rule->interactive_config, "chatbot:{$rule->id}",
            );

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
            $autoresponder->media_url, $autoresponder->interactive_config, "autoresponder:{$autoresponder->id}",
        );
    }
}
