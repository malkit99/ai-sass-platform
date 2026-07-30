<?php

namespace App\Http\Controllers\Api\Whatsapp\PublicApi;

use App\Models\Account;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappCreditBalance;
use App\Models\WhatsappCreditLedger;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Shared by MessageApiController (1:1 sends) and GroupApiController (group
 * sends) — same credit check-and-decrement as every other send path in this
 * app (MessageController::store, SendCampaignMessageJob, SendAutoReplyJob).
 * $to is either a phone number or a full group JID ({id}@g.us) — the bridge's
 * sendMessage/toJid already passes a JID through unchanged (see
 * sessionManager.js), so no branching needed here.
 */
trait SendsWhatsappViaApi
{
    private function sendAndDecrement(
        Channel $channel, string $to, string $type, ?string $body,
        ?string $mediaUrl, ?string $mediaType, ?array $interactiveConfig,
    ): array {
        $account = Account::withoutGlobalScopes()->find($channel->account_id);
        $balance = WhatsappCreditBalance::forAccount($account);

        if ($balance->credits_remaining < 1) {
            throw new HttpException(402, 'No WhatsApp credits remaining on this account\'s plan.');
        }

        if ($channel->status !== Channel::STATUS_CONNECTED) {
            throw new HttpException(409, 'This WhatsApp account is disconnected.');
        }

        $conversation = Conversation::withoutGlobalScopes()->firstOrCreate(
            ['channel_id' => $channel->id, 'contact_phone' => $to],
            ['account_id' => $channel->account_id, 'last_message_at' => now()],
        );

        $message = $conversation->messages()->create([
            'direction' => Message::DIRECTION_OUT,
            'type' => $type,
            'body' => $body,
            'media_url' => $mediaUrl,
            'status' => 'sending',
            'sent_by' => "api:{$channel->id}",
        ]);

        try {
            $result = $this->bridge->sendMessage($channel->id, $to, $type, $body, $mediaUrl, $mediaType, $interactiveConfig);

            $message->update(['status' => 'sent', 'external_id' => $result['message_id'] ?? null]);
            $conversation->update(['last_message_at' => now()]);

            WhatsappCreditLedger::record($account, -1, WhatsappCreditLedger::REASON_MESSAGE_SENT);
        } catch (\Throwable $e) {
            $message->update(['status' => 'failed', 'error' => $e->getMessage()]);

            throw new HttpException(502, 'Failed to send message: '.$e->getMessage());
        }

        return ['ok' => true, 'message_id' => $message->id, 'external_id' => $message->external_id];
    }
}
