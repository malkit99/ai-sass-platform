<?php

namespace App\Jobs\Whatsapp;

use App\Models\Account;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use App\Models\WhatsappCreditBalance;
use App\Models\WhatsappCreditLedger;
use App\Services\Whatsapp\BridgeClient;
use App\Services\Whatsapp\Spintax;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One job per recipient — dispatched with a cumulative delay computed by
 * CampaignController::store() so sends are spread out per the campaign's
 * min/max interval instead of firing all at once (anti-ban pacing, see
 * 11-unofficial-whatsapp.md). A single big loop-with-sleep job would block a
 * queue worker for the whole campaign; per-recipient jobs with `delay()` don't.
 */
class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $recipientId) {}

    public function handle(BridgeClient $bridge): void
    {
        $recipient = WhatsappCampaignRecipient::find($this->recipientId);
        if (! $recipient || $recipient->status !== WhatsappCampaignRecipient::STATUS_PENDING) {
            return;
        }

        $campaign = WhatsappCampaign::withoutGlobalScopes()->find($recipient->campaign_id);
        if (! $campaign || $campaign->status === WhatsappCampaign::STATUS_CANCELLED) {
            return;
        }

        // A pause can't un-queue jobs already delayed — so a paused recipient's
        // job just keeps rescheduling itself instead of sending, until either
        // resumed (status back to running) or the campaign is cancelled above.
        if ($campaign->status === WhatsappCampaign::STATUS_PAUSED) {
            self::dispatch($this->recipientId)->delay(now()->addSeconds(30));

            return;
        }

        // The "Time Post" delay is baked into when this job fires, not into
        // the status field — flip scheduled -> running the moment the first
        // recipient's job actually runs, so the campaign list reflects reality.
        if ($campaign->status === WhatsappCampaign::STATUS_SCHEDULED) {
            $campaign->update(['status' => WhatsappCampaign::STATUS_RUNNING]);
        }

        $channel = Channel::withoutGlobalScopes()->find($campaign->channel_id);
        $account = Account::withoutGlobalScopes()->find($campaign->account_id);
        $balance = WhatsappCreditBalance::forAccount($account);

        if (! $channel || $channel->status !== Channel::STATUS_CONNECTED) {
            $recipient->update(['status' => WhatsappCampaignRecipient::STATUS_FAILED, 'error' => 'Channel not connected']);
            $this->maybeCompleteCampaign($campaign);

            return;
        }

        if ($balance->credits_remaining < 1) {
            $recipient->update(['status' => WhatsappCampaignRecipient::STATUS_FAILED, 'error' => 'No WhatsApp credits remaining']);
            $this->maybeCompleteCampaign($campaign);

            return;
        }

        $body = $campaign->spintax_enabled ? Spintax::render($campaign->body) : $campaign->body;

        try {
            $bridge->sendMessage($channel->id, $recipient->phone, $campaign->message_type, $body, $campaign->media_url, $campaign->media_type);

            $conversation = Conversation::withoutGlobalScopes()->firstOrCreate(
                ['channel_id' => $channel->id, 'contact_phone' => $recipient->phone],
                ['account_id' => $channel->account_id, 'last_message_at' => now()]
            );

            $conversation->messages()->create([
                'direction' => Message::DIRECTION_OUT,
                'type' => $campaign->message_type,
                'body' => $body,
                'media_url' => $campaign->media_url,
                'status' => 'sent',
                'sent_by' => 'campaign:'.$campaign->id,
            ]);

            $conversation->update(['last_message_at' => now()]);

            $recipient->update(['status' => WhatsappCampaignRecipient::STATUS_SENT, 'sent_at' => now()]);

            WhatsappCreditLedger::record($account, -1, WhatsappCreditLedger::REASON_BULK_SENT);
        } catch (\Throwable $e) {
            $recipient->update(['status' => WhatsappCampaignRecipient::STATUS_FAILED, 'error' => $e->getMessage()]);
        }

        $this->maybeCompleteCampaign($campaign);
    }

    private function maybeCompleteCampaign(WhatsappCampaign $campaign): void
    {
        $stillPending = $campaign->recipients()->where('status', WhatsappCampaignRecipient::STATUS_PENDING)->exists();

        if (! $stillPending) {
            $campaign->update(['status' => WhatsappCampaign::STATUS_COMPLETED]);
        }
    }
}
