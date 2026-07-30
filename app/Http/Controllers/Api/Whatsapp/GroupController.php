<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\WhatsappGroup;
use App\Services\Whatsapp\BridgeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GroupController extends Controller
{
    public function __construct(private readonly BridgeClient $bridge) {}

    /**
     * Known groups (screenshot 90's list) are scoped to a single account —
     * discovered passively via WebhookController::handleGroupSeen(), not a
     * live "list all my groups" call.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', WhatsappGroup::class);

        $data = $request->validate(['channel_id' => ['required', 'integer', 'exists:channels,id']]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        return WhatsappGroup::query()->where('channel_id', $channel->id)->latest()->get();
    }

    /**
     * "Download" (screenshot 90) — fetch-fresh-then-export as one action, no
     * separate sync step in the UI. Replaces this group's cached participants
     * with whatever the bridge's live groupMetadata call just returned.
     */
    public function export(WhatsappGroup $group)
    {
        $this->authorize('view', $group);

        $result = $this->bridge->fetchGroupParticipants($group->channel_id, $group->group_jid);
        $participants = $result['participants'] ?? [];
        $syncedAt = Carbon::now();

        $group->participants()->delete();
        $group->participants()->createMany(array_map(
            fn (array $p) => ['phone' => $p['phone'], 'admin' => $p['admin'] ?? null, 'synced_at' => $syncedAt],
            $participants,
        ));

        $group->update([
            'name' => $result['name'] ?? $group->name,
            'participant_count' => count($participants),
            'last_synced_at' => $syncedAt,
        ]);

        return response()->streamDownload(function () use ($participants) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Phone', 'Admin']);

            foreach ($participants as $participant) {
                fputcsv($out, [$participant['phone'], $participant['admin'] ?? '']);
            }

            fclose($out);
        }, ($group->name ?: $group->group_jid).'-participants.csv');
    }
}
