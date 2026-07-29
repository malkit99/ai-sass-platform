<?php

namespace App\Services\Whatsapp;

use App\Jobs\Whatsapp\SendCampaignMessageJob;
use App\Models\Channel;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Turns a recipient phone list into per-recipient delayed jobs — shared by
 * CampaignController::store() (the initial run) and
 * ProcessRecurringWhatsappCampaigns (each recurring re-run), so the anti-ban
 * pacing + allowed-hours math only lives in one place.
 */
class CampaignDispatcher
{
    // Safety bound on how far forward to search for an allowed hour, so a
    // pathological input (e.g. an empty-after-filtering list) can't loop forever.
    private const MAX_HOUR_SEARCH = 24 * 14;

    // Warm-up daily-send-cap ramp: doubles each day from a 20/day floor until
    // it plateaus at 300/day (day 1: 20, day 2: 40, day 3: 80, day 4: 160,
    // day 5+: 300). Tracked per-channel (Channel::warmup_started_at), not per
    // campaign, so it reflects the number's real sending history — running
    // two warm-up campaigns back to back on the same channel shares one cap.
    private const WARM_UP_BASE_DAILY_CAP = 20;

    private const WARM_UP_MAX_DAILY_CAP = 300;

    // Safety bound on how many days forward to search for spare daily-cap
    // capacity, so a huge recipient list piling up behind other channel
    // traffic can't loop forever — see nextAllowedTime's MAX_HOUR_SEARCH.
    private const MAX_WARM_UP_DAY_SEARCH = 400;

    public function dispatchRecipients(
        WhatsappCampaign $campaign,
        Channel $channel,
        array $phones,
        int $minIntervalSeconds,
        int $maxIntervalSeconds,
        ?array $allowedHours,
        CarbonInterface $startAt,
    ): void {
        if ($campaign->warm_up_mode) {
            $this->dispatchWithWarmUpCap($campaign, $channel, $phones, $minIntervalSeconds, $maxIntervalSeconds, $allowedHours, $startAt);

            return;
        }

        $cumulativeDelay = 0;

        foreach ($phones as $phone) {
            $sendAt = $startAt->copy()->addSeconds($cumulativeDelay);
            if ($allowedHours) {
                $sendAt = $this->nextAllowedTime($sendAt, $allowedHours);
            }

            $this->queueRecipient($campaign, $phone, $sendAt);

            $cumulativeDelay += random_int($minIntervalSeconds, $maxIntervalSeconds);
        }
    }

    private function dispatchWithWarmUpCap(
        WhatsappCampaign $campaign,
        Channel $channel,
        array $phones,
        int $minIntervalSeconds,
        int $maxIntervalSeconds,
        ?array $allowedHours,
        CarbonInterface $startAt,
    ): void {
        if (! $channel->warmup_started_at) {
            $channel->forceFill(['warmup_started_at' => now()])->save();
            $channel->refresh();
        }

        $remaining = $phones;
        $day = $startAt->copy();

        for ($i = 0; $remaining && $i < self::MAX_WARM_UP_DAY_SEARCH; $i++) {
            $cap = $this->warmUpCapFor($channel, $day);
            $alreadyScheduled = $this->countScheduledOnDay($channel, $day);
            $capacityToday = max(0, $cap - $alreadyScheduled);

            $todaysBatch = array_splice($remaining, 0, $capacityToday);
            $sendAt = $day->copy();

            foreach ($todaysBatch as $phone) {
                $slot = $allowedHours ? $this->nextAllowedTime($sendAt->copy(), $allowedHours) : $sendAt->copy();

                $this->queueRecipient($campaign, $phone, $slot);

                $sendAt->addSeconds(random_int($minIntervalSeconds, $maxIntervalSeconds));
            }

            if ($remaining) {
                $day = $day->copy()->addDay()->startOfDay();
            }
        }

        // MAX_WARM_UP_DAY_SEARCH exhausted (only possible if other channel
        // traffic keeps saturating every day's cap) — send whatever's left
        // immediately rather than dropping recipients silently.
        foreach ($remaining as $phone) {
            $this->queueRecipient($campaign, $phone, $day);
        }
    }

    private function warmUpCapFor(Channel $channel, CarbonInterface $day): int
    {
        $dayNumber = $channel->warmup_started_at->copy()->startOfDay()->diffInDays($day->copy()->startOfDay()) + 1;

        return min(self::WARM_UP_MAX_DAILY_CAP, self::WARM_UP_BASE_DAILY_CAP * (2 ** ($dayNumber - 1)));
    }

    private function countScheduledOnDay(Channel $channel, CarbonInterface $day): int
    {
        return WhatsappCampaignRecipient::query()
            ->join('whatsapp_campaigns', 'whatsapp_campaigns.id', '=', 'whatsapp_campaign_recipients.campaign_id')
            ->where('whatsapp_campaigns.channel_id', $channel->id)
            ->whereBetween('whatsapp_campaign_recipients.scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->count();
    }

    private function queueRecipient(WhatsappCampaign $campaign, string $phone, CarbonInterface $sendAt): void
    {
        $recipient = $campaign->recipients()->create(['phone' => $phone, 'scheduled_at' => $sendAt]);

        SendCampaignMessageJob::dispatch($recipient->id)->delay($sendAt);
    }

    private function nextAllowedTime(Carbon $time, array $allowedHours): Carbon
    {
        for ($i = 0; $i < self::MAX_HOUR_SEARCH; $i++) {
            if (in_array($time->hour, $allowedHours, true)) {
                return $time;
            }

            $time = $time->copy()->addHour()->minute(0)->second(0);
        }

        return $time;
    }

    public function nextRunAt(CarbonInterface $from, string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            'monthly' => $from->copy()->addMonthNoOverflow(),
            default => $from->copy()->addDay(),
        };
    }
}
