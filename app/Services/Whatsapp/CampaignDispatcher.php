<?php

namespace App\Services\Whatsapp;

use App\Jobs\Whatsapp\SendCampaignMessageJob;
use App\Models\WhatsappCampaign;
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

    public function dispatchRecipients(
        WhatsappCampaign $campaign,
        array $phones,
        int $minIntervalSeconds,
        int $maxIntervalSeconds,
        ?array $allowedHours,
        CarbonInterface $startAt,
    ): void {
        $cumulativeDelay = 0;

        foreach ($phones as $phone) {
            $recipient = $campaign->recipients()->create(['phone' => $phone]);

            $sendAt = $startAt->copy()->addSeconds($cumulativeDelay);
            if ($allowedHours) {
                $sendAt = $this->nextAllowedTime($sendAt, $allowedHours);
            }

            SendCampaignMessageJob::dispatch($recipient->id)->delay($sendAt);

            $cumulativeDelay += random_int($minIntervalSeconds, $maxIntervalSeconds);
        }
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
