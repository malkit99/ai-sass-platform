<?php

namespace App\Http\Controllers\Api\Whatsapp\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\Whatsapp\BridgeClient;
use Illuminate\Http\Request;

/**
 * Public REST API — Instance API (screenshots 38-39). Every method operates
 * on a channel already created through the app's own "Add account" flow
 * (see ResolvesPublicApiChannel's docblock for why instance creation stays
 * UI-only) — these are thin wrappers around the exact same BridgeClient
 * calls ChannelController already makes.
 */
class InstanceApiController extends Controller
{
    use ResolvesPublicApiChannel;

    public function __construct(private readonly BridgeClient $bridge) {}

    public function createInstance(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'instance');

        $this->bridge->createInstance($channel->id);
        $channel->update(['status' => Channel::STATUS_CONNECTING]);

        return response()->json(['instance_id' => $channel->id, 'status' => $channel->status]);
    }

    public function getQrCode(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'instance');

        return response()->json(['qr' => $this->bridge->getQrCode($channel->id)]);
    }

    public function setWebhook(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'instance');

        $data = $request->validate([
            'webhook_url' => ['required', 'url'],
            'enable' => ['required'],
        ]);

        $channel->update([
            'external_webhook_url' => $data['webhook_url'],
            'external_webhook_enabled' => filter_var($data['enable'], FILTER_VALIDATE_BOOLEAN),
        ]);

        return response()->json(['ok' => true]);
    }

    public function reboot(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'instance');

        $this->bridge->logout($channel->id);
        $channel->update(['status' => Channel::STATUS_DISCONNECTED, 'connected_at' => null]);
        $this->bridge->createInstance($channel->id);
        $channel->update(['status' => Channel::STATUS_CONNECTING]);

        return response()->json(['ok' => true, 'status' => $channel->status]);
    }

    /**
     * The reference app's reset_instance also "changes the Instance ID" —
     * here instance_id is the channel's own DB primary key, which can't
     * safely change without breaking every FK referencing it, so this is a
     * logout + session wipe only, same instance_id. Documented on the API
     * page itself too, not silently different from what's advertised.
     */
    public function resetInstance(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'instance');

        $this->bridge->logout($channel->id);
        $channel->update(['status' => Channel::STATUS_DISCONNECTED, 'connected_at' => null]);

        return response()->json(['ok' => true, 'instance_id' => $channel->id]);
    }

    public function reconnect(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'instance');

        $this->bridge->createInstance($channel->id);
        $channel->update(['status' => Channel::STATUS_CONNECTING]);

        return response()->json(['ok' => true, 'status' => $channel->status]);
    }
}
