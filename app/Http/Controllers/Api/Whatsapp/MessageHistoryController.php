<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\Request;

/**
 * Flat, filterable log of every message (in + out) sent through a given
 * account — not a per-contact chat thread. Reads through `messages`/
 * `conversations` (already written by MessageController, WebhookController's
 * inbound handler, SendAutoReplyJob, SendCampaignMessageJob, and the Public
 * API's SendsWhatsappViaApi), so this has data from every send path in the
 * app with no new writes needed.
 */
class MessageHistoryController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'direction' => ['nullable', 'string', 'in:in,out'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        return Message::query()
            ->with('conversation:id,contact_phone,contact_name')
            ->whereHas('conversation', fn ($q) => $q->where('channel_id', $channel->id))
            ->when($data['direction'] ?? null, fn ($q, $direction) => $q->where('direction', $direction))
            ->when($data['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('body', 'like', "%{$search}%")
                ->orWhereHas('conversation', fn ($q) => $q
                    ->where('contact_phone', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%"))))
            ->latest('created_at')
            ->paginate($data['per_page'] ?? 20);
    }
}
