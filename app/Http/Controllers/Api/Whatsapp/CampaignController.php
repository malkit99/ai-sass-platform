<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use App\Models\WhatsappContact;
use App\Models\WhatsappContactGroup;
use App\Models\WhatsappTemplate;
use App\Rules\ValidMobileNumber;
use App\Services\Whatsapp\CampaignDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    // Server-enforced bounds regardless of what the client requests — the core
    // anti-ban pacing requirement from 05-integrations.md / 11-unofficial-whatsapp.md.
    // The UI now offers a 1-3600s picker (screenshot 76), but a sub-3s floor is
    // still silently bumped up here — it's not a safe interval to actually run.
    private const MIN_INTERVAL_FLOOR = 3;

    private const MAX_INTERVAL_CEILING = 3600;

    public function __construct(private readonly CampaignDispatcher $dispatcher) {}

    public function index()
    {
        $this->authorize('viewAny', WhatsappCampaign::class);

        return WhatsappCampaign::query()
            ->withCount([
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->where('status', WhatsappCampaignRecipient::STATUS_SENT),
                'recipients as failed_count' => fn ($q) => $q->where('status', WhatsappCampaignRecipient::STATUS_FAILED),
                'recipients as queued_count' => fn ($q) => $q->where('status', WhatsappCampaignRecipient::STATUS_PENDING),
            ])
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappCampaign::class);

        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'name' => ['required', 'string', 'max:255'],
            'message_type' => ['required', 'in:text,media,template'],
            'template_id' => ['required_if:message_type,template', 'nullable', 'integer', 'exists:whatsapp_templates,id'],
            'body' => ['required_if:message_type,text', 'nullable', 'string', 'max:4096'],
            'media_url' => ['required_if:message_type,media', 'nullable', 'string', 'url'],
            'spintax_enabled' => ['boolean'],
            'warm_up_mode' => ['boolean'],
            'min_interval_seconds' => ['required', 'integer', 'min:1', 'max:3600'],
            'max_interval_seconds' => ['required', 'integer', 'gte:min_interval_seconds', 'max:3600'],
            'contact_group_id' => ['required_without:recipients', 'nullable', 'integer', 'exists:whatsapp_contact_groups,id'],
            'recipients' => ['required_without:contact_group_id', 'nullable', 'array', 'min:1', 'max:5000'],
            'recipients.*' => ['distinct', new ValidMobileNumber],
            // "Time Post" — null/omitted means run immediately.
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
            // "Schedule Time" hour filter (screenshot 76) — empty/omitted means any hour.
            'allowed_hours' => ['nullable', 'array'],
            'allowed_hours.*' => ['integer', 'min:0', 'max:23'],
            'recurring_frequency' => ['nullable', 'in:'.implode(',', WhatsappCampaign::RECURRING_FREQUENCIES)],
        ]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        $mediaType = null;

        // A "Buttons"/"List message"/"Poll"/"Template" pick (screenshot 76's
        // filter row) references a saved template by id rather than trusting
        // client-echoed body/media_url — same authoritative pattern as
        // MessageController's single-send template path.
        if ($data['message_type'] === 'template') {
            $template = WhatsappTemplate::findOrFail($data['template_id']);

            if (! in_array($template->type, WhatsappTemplate::SENDABLE_TYPES, true)) {
                throw ValidationException::withMessages([
                    'template_id' => ['Bulk-sending this template type (buttons/list/poll/carousel) isn\'t supported yet — coming in a future update.'],
                ]);
            }

            $data['body'] = $template->body;
            $data['media_url'] = $template->media_url;
            $mediaType = $template->mediaKind();
            $data['message_type'] = $mediaType ? 'media' : 'text';
        }

        if (! empty($data['contact_group_id'])) {
            $group = WhatsappContactGroup::findOrFail($data['contact_group_id']);
            $this->authorize('view', $group);

            // Known-invalid numbers (bad format, or previously failed a live
            // WhatsApp-registration check) are skipped rather than burning a
            // credit and a send attempt on a number that will only bounce.
            $recipients = $group->contacts()
                ->where('status', '!=', WhatsappContact::STATUS_INVALID)
                ->limit(5000)
                ->pluck('phone')
                ->all();

            if (! $recipients) {
                throw ValidationException::withMessages([
                    'contact_group_id' => ['This contact group has no valid contacts to message.'],
                ]);
            }
        } else {
            $recipients = array_values(array_unique($data['recipients']));
        }

        $minInterval = max(self::MIN_INTERVAL_FLOOR, $data['min_interval_seconds']);
        $maxInterval = min(self::MAX_INTERVAL_CEILING, max($minInterval, $data['max_interval_seconds']));

        // Warm-up mode widens the gap further — pacing is the whole point of the
        // feature, not just a UI label (see 05-integrations.md anti-ban note).
        if ($data['warm_up_mode'] ?? false) {
            $minInterval *= 2;
            $maxInterval *= 2;
        }

        $startAt = ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : now();
        $allowedHours = ! empty($data['allowed_hours']) ? array_values(array_unique($data['allowed_hours'])) : null;

        $campaign = WhatsappCampaign::create([
            'channel_id' => $channel->id,
            'contact_group_id' => $data['contact_group_id'] ?? null,
            'name' => $data['name'],
            'message_type' => $data['message_type'],
            'body' => $data['body'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'media_type' => $mediaType,
            'spintax_enabled' => $data['spintax_enabled'] ?? false,
            'warm_up_mode' => $data['warm_up_mode'] ?? false,
            'min_interval_seconds' => $minInterval,
            'max_interval_seconds' => $maxInterval,
            'scheduled_at' => $startAt->isFuture() ? $startAt : null,
            'allowed_hours' => $allowedHours,
            'recurring_frequency' => $data['recurring_frequency'] ?? null,
            'next_run_at' => ! empty($data['recurring_frequency']) ? $this->dispatcher->nextRunAt($startAt, $data['recurring_frequency']) : null,
            'status' => $startAt->isFuture() ? WhatsappCampaign::STATUS_SCHEDULED : WhatsappCampaign::STATUS_RUNNING,
        ]);

        $this->dispatcher->dispatchRecipients($campaign, $recipients, $minInterval, $maxInterval, $allowedHours, $startAt);

        return response()->json($campaign->load('recipients'), 201);
    }

    public function show(WhatsappCampaign $campaign)
    {
        $this->authorize('view', $campaign);

        return $campaign->load('recipients');
    }

    /**
     * Rename only — the campaign's recipients and per-recipient send delays
     * are already computed and queued at creation time (see store()), so
     * changing the message, timing, or recipient list after the fact isn't
     * safe without a proper "scheduled, not yet started" state, which this
     * module doesn't have yet.
     */
    public function update(Request $request, WhatsappCampaign $campaign)
    {
        $this->authorize('update', $campaign);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $campaign->update($data);

        return $campaign;
    }

    /**
     * Already-dispatched delayed jobs can't be un-queued, so this just flips
     * the status — SendCampaignMessageJob checks it and keeps rescheduling
     * a paused recipient's job every 30s instead of sending, until resumed.
     */
    public function pause(WhatsappCampaign $campaign)
    {
        $this->authorize('update', $campaign);

        if ($campaign->status !== WhatsappCampaign::STATUS_RUNNING) {
            throw ValidationException::withMessages(['status' => ['Only a running campaign can be paused.']]);
        }

        $campaign->update(['status' => WhatsappCampaign::STATUS_PAUSED]);

        return $campaign;
    }

    public function resume(WhatsappCampaign $campaign)
    {
        $this->authorize('update', $campaign);

        if ($campaign->status !== WhatsappCampaign::STATUS_PAUSED) {
            throw ValidationException::withMessages(['status' => ['Only a paused campaign can be resumed.']]);
        }

        $campaign->update(['status' => WhatsappCampaign::STATUS_RUNNING]);

        return $campaign;
    }

    /**
     * Permanently removes the campaign. If it's still active, first cancel it
     * (stop pending recipients so any already-delayed jobs no-op instead of
     * racing the delete) before the row — and its recipients, via cascade —
     * is actually dropped.
     */
    public function destroy(WhatsappCampaign $campaign)
    {
        $this->authorize('delete', $campaign);

        if (in_array($campaign->status, [WhatsappCampaign::STATUS_RUNNING, WhatsappCampaign::STATUS_PAUSED, WhatsappCampaign::STATUS_SCHEDULED], true)) {
            $campaign->update(['status' => WhatsappCampaign::STATUS_CANCELLED]);
            $campaign->recipients()->where('status', WhatsappCampaignRecipient::STATUS_PENDING)->delete();
        }

        $campaign->delete();

        return response()->noContent();
    }
}
