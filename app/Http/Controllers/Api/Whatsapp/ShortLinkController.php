<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\WhatsappShortLink;
use App\Rules\ValidMobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShortLinkController extends Controller
{
    /**
     * Saved links (screenshot 79's "Saved Links" panel) are scoped to a
     * single account — the UI only shows this list once one is selected.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', WhatsappShortLink::class);

        $data = $request->validate(['channel_id' => ['required', 'integer', 'exists:channels,id']]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        return WhatsappShortLink::query()->where('channel_id', $channel->id)->latest()->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappShortLink::class);

        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'reference_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new ValidMobileNumber()],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        do {
            $slug = Str::random(8);
        } while (WhatsappShortLink::withoutGlobalScopes()->where('slug', $slug)->exists());

        $link = WhatsappShortLink::create([...$data, 'slug' => $slug]);

        return response()->json($link, 201);
    }

    public function destroy(WhatsappShortLink $link)
    {
        $this->authorize('delete', $link);

        $link->delete();

        return response()->noContent();
    }
}
