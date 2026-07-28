<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappContact;
use App\Models\WhatsappContactGroup;
use App\Services\Whatsapp\CampaignDispatcher;
use Illuminate\Console\Command;

/**
 * "Enable Recurring Schedule" (screenshot 76) — for every campaign whose
 * next_run_at is due, spins off a fresh child campaign (fresh recipient rows
 * + fresh delayed jobs) reusing the original's message/config, then pushes
 * the original's next_run_at forward by its frequency. The original campaign
 * itself is never re-dispatched — only its config is cloned each cycle, so
 * each run gets its own independent set of recipients/sent/failed counts.
 */
class ProcessRecurringWhatsappCampaigns extends Command
{
    protected $signature = 'app:process-recurring-whatsapp-campaigns';

    protected $description = 'Spin off the next scheduled run for every due recurring WhatsApp campaign';

    public function handle(CampaignDispatcher $dispatcher): void
    {
        // No authenticated user in a scheduled/CLI run — BelongsToTenant's
        // global scope is a no-op, intentionally checking every tenant in one pass.
        $due = WhatsappCampaign::whereNotNull('recurring_frequency')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->where('status', '!=', WhatsappCampaign::STATUS_CANCELLED)
            ->get();

        foreach ($due as $original) {
            $channel = Channel::withoutGlobalScopes()->find($original->channel_id);
            $phones = $this->resolveRecipients($original);

            if (! $channel || $channel->status !== Channel::STATUS_CONNECTED || ! $phones) {
                $this->warn("Skipped recurring run for campaign {$original->id} — ".(! $phones ? 'no recipients' : 'channel not connected'));
                $original->update(['next_run_at' => $dispatcher->nextRunAt(now(), $original->recurring_frequency)]);

                continue;
            }

            $child = WhatsappCampaign::create([
                'account_id' => $original->account_id,
                'channel_id' => $original->channel_id,
                'contact_group_id' => $original->contact_group_id,
                'parent_campaign_id' => $original->parent_campaign_id ?? $original->id,
                'name' => $original->name.' ('.now()->format('Y-m-d').')',
                'message_type' => $original->message_type,
                'body' => $original->body,
                'media_url' => $original->media_url,
                'media_type' => $original->media_type,
                'spintax_enabled' => $original->spintax_enabled,
                'warm_up_mode' => $original->warm_up_mode,
                'min_interval_seconds' => $original->min_interval_seconds,
                'max_interval_seconds' => $original->max_interval_seconds,
                'allowed_hours' => $original->allowed_hours,
                'status' => WhatsappCampaign::STATUS_RUNNING,
            ]);

            $dispatcher->dispatchRecipients(
                $child, $phones, $original->min_interval_seconds, $original->max_interval_seconds,
                $original->allowed_hours, now(),
            );

            $original->update(['next_run_at' => $dispatcher->nextRunAt($original->next_run_at, $original->recurring_frequency)]);

            $this->info("Spun off campaign {$child->id} \"{$child->name}\" from recurring campaign {$original->id} (".count($phones).' recipients).');
        }

        $this->info("Checked {$due->count()} recurring campaign(s).");
    }

    private function resolveRecipients(WhatsappCampaign $campaign): array
    {
        if ($campaign->contact_group_id) {
            $group = WhatsappContactGroup::withoutGlobalScopes()->find($campaign->contact_group_id);

            return $group
                ? $group->contacts()->where('status', '!=', WhatsappContact::STATUS_INVALID)->limit(5000)->pluck('phone')->all()
                : [];
        }

        return $campaign->recipients()->distinct()->pluck('phone')->all();
    }
}
