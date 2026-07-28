<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\Whatsapp\BridgeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChannelController extends Controller
{
    public function __construct(private readonly BridgeClient $bridge) {}

    public function index()
    {
        $this->authorize('viewAny', Channel::class);

        $account = Auth::user()->account;

        return response()->json([
            'channels' => Channel::query()->latest()->get(),
            'limit' => $account->plan?->limits['whatsapp_max_numbers'] ?? null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Channel::class);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $account = Auth::user()->account;
        $limit = $account->plan?->limits['whatsapp_max_numbers'] ?? null;

        // Every channel row counts against the limit, connected or not — a
        // disconnected-but-undeleted number still occupies a slot until the
        // user actually deletes it (see ChannelController::destroy).
        if ($limit !== null && Channel::query()->count() >= $limit) {
            throw ValidationException::withMessages([
                'name' => ["You've reached your plan's WhatsApp account limit ({$limit})."],
            ]);
        }

        $channel = Channel::create([
            'type' => Channel::TYPE_WHATSAPP_UNOFFICIAL,
            'name' => $data['name'] ?? null,
            'status' => Channel::STATUS_CONNECTING,
        ]);

        $this->bridge->createInstance($channel->id);

        return response()->json($channel, 201);
    }

    public function show(Channel $channel)
    {
        $this->authorize('view', $channel);

        return $channel;
    }

    /**
     * User-supplied label — WhatsApp's multi-device protocol never exposes
     * the account's own display name over the socket (see Channel::getDisplayNameAttribute),
     * so this is the only way a name ever gets attached to a channel.
     */
    public function update(Request $request, Channel $channel)
    {
        $this->authorize('update', $channel);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $channel->update($data);

        return $channel;
    }

    /**
     * (Re)starts the bridge instance for an existing channel — idempotent on the
     * bridge side (see BridgeClient::createInstance). Needed any time the bridge's
     * in-memory session for this channel is gone but the `channels` row survived
     * (bridge process restarted, or a previous connect attempt was never
     * finished) — without this, opening the Connect dialog on such a channel
     * would just poll a bridge instance that no longer exists and never see a QR.
     */
    public function connect(Channel $channel)
    {
        $this->authorize('update', $channel);

        $this->bridge->createInstance($channel->id);

        $channel->update(['status' => Channel::STATUS_CONNECTING]);

        return response()->json($channel);
    }

    /**
     * Polled by the frontend while the "Connect WhatsApp" dialog is open.
     */
    public function qr(Channel $channel)
    {
        $this->authorize('view', $channel);

        return response()->json(['qr' => $this->bridge->getQrCode($channel->id)]);
    }

    public function pairingCode(Request $request, Channel $channel)
    {
        $this->authorize('view', $channel);

        $data = $request->validate([
            'phone_number' => ['required', 'string'],
        ]);

        return response()->json(['code' => $this->bridge->requestPairingCode($channel->id, $data['phone_number'])]);
    }

    /**
     * Polled by the frontend to reflect live connection state; the bridge is the
     * source of truth for "connected/connecting", the webhook keeps our own
     * `channels.status` column roughly in sync for listing screens that don't poll.
     */
    public function status(Channel $channel)
    {
        $this->authorize('view', $channel);

        return response()->json($this->bridge->status($channel->id));
    }

    /**
     * Logs out of WhatsApp but keeps the `channels` row (and its conversation
     * history) so the same instance can be reconnected later without losing
     * data or its plan-limit slot being freed. See destroy() for a full delete.
     */
    public function disconnect(Channel $channel)
    {
        $this->authorize('update', $channel);

        $this->bridge->logout($channel->id);

        $channel->update(['status' => Channel::STATUS_DISCONNECTED, 'connected_at' => null]);

        return response()->json($channel);
    }

    /**
     * Permanently removes the WhatsApp account — logs out of the bridge (if
     * still connected) and deletes the `channels` row, which cascades to its
     * conversations/messages. Frees up the plan's whatsapp_max_numbers slot.
     */
    public function destroy(Channel $channel)
    {
        $this->authorize('delete', $channel);

        $this->bridge->logout($channel->id);

        $channel->delete();

        return response()->noContent();
    }
}
