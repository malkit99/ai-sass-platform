<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAutoresponder;
use Illuminate\Http\Request;

class AutoresponderController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', WhatsappAutoresponder::class);

        return WhatsappAutoresponder::query()->latest()->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappAutoresponder::class);

        $data = $this->validated($request);

        return response()->json(WhatsappAutoresponder::create($data), 201);
    }

    public function update(Request $request, WhatsappAutoresponder $autoresponder)
    {
        $this->authorize('update', $autoresponder);

        $autoresponder->update($this->validated($request));

        return $autoresponder;
    }

    public function destroy(WhatsappAutoresponder $autoresponder)
    {
        $this->authorize('delete', $autoresponder);

        $autoresponder->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        // Null channel_id = applies to every channel on this account (the
        // reference app's "Apply for all accounts" option, see screenshot 34).
        return $request->validate([
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
            'enabled' => ['boolean'],
            'message_type' => ['required', 'in:text,media'],
            'body' => ['required_if:message_type,text', 'nullable', 'string', 'max:4096'],
            'media_url' => ['required_if:message_type,media', 'nullable', 'string', 'url'],
        ]);
    }
}
