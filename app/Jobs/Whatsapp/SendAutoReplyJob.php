<?php

namespace App\Jobs\Whatsapp;

use App\Models\Account;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappCreditBalance;
use App\Models\WhatsappCreditLedger;
use App\Services\Whatsapp\AutoresponderVariables;
use App\Services\Whatsapp\BridgeClient;
use App\Services\Whatsapp\Spintax;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends a chatbot/autoresponder reply on the queue instead of inline inside
 * WebhookController — an inbound message's webhook POST is the *bridge*
 * waiting on Laravel with its own 10s timeout (webhookClient.js); calling
 * back into the bridge to send a reply synchronously from within that same
 * request risks the reply itself (especially media — download+upload can
 * exceed 10s) blowing past the bridge's own wait, which then logs a webhook
 * delivery timeout even though the inbound message was received fine.
 * Confirmed live: text replies stayed under 10s, an image reply didn't.
 */
class SendAutoReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $channelId,
        private readonly int $conversationId,
        private readonly string $type,
        private readonly ?string $body,
        private readonly ?string $mediaUrl,
        private readonly ?array $interactiveConfig,
    ) {}

    public function handle(BridgeClient $bridge): void
    {
        $channel = Channel::withoutGlobalScopes()->find($this->channelId);
        $conversation = Conversation::withoutGlobalScopes()->find($this->conversationId);

        if (! $channel || ! $conversation || $channel->status !== Channel::STATUS_CONNECTED) {
            return;
        }

        $account = Account::withoutGlobalScopes()->find($channel->account_id);
        $balance = WhatsappCreditBalance::forAccount($account);

        if ($balance->credits_remaining < 1) {
            return;
        }

        $body = AutoresponderVariables::render($this->body, $conversation->contact_phone);
        $body = Spintax::render($body);

        try {
            $bridge->sendMessage($channel->id, $conversation->contact_phone, $this->type, $body, $this->mediaUrl, null, $this->interactiveConfig);

            $conversation->messages()->create([
                'direction' => Message::DIRECTION_OUT,
                'type' => $this->type,
                'body' => $body,
                'media_url' => $this->mediaUrl,
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
