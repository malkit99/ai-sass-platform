<?php

namespace App\Http\Controllers\Api\Whatsapp\PublicApi;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\BridgeClient;
use Illuminate\Http\Request;

/**
 * Public REST API — Group API (screenshots 49-50). send_group shares the
 * same credit check-and-decrement as the Send Direct Message API — a group
 * broadcast still costs one credit per send, same as every other outbound
 * message path in this app.
 */
class GroupApiController extends Controller
{
    use ResolvesPublicApiChannel, SendsWhatsappViaApi;

    public function __construct(private readonly BridgeClient $bridge) {}

    public function getGroups(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'groups');

        return response()->json(['groups' => $this->bridge->listGroups($channel->id)]);
    }

    public function sendGroup(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'groups');

        $data = $request->validate([
            'group_id' => ['required', 'string', 'regex:/@g\.us$/'],
            'type' => ['required', 'in:text,media'],
            'message' => ['nullable', 'string', 'max:4096'],
            'media_url' => ['required_if:type,media', 'nullable', 'string', 'url'],
        ]);

        $result = $this->sendAndDecrement(
            $channel, $data['group_id'], $data['type'], $data['message'] ?? null,
            $data['media_url'] ?? null, null, null,
        );

        return response()->json($result, 201);
    }
}
