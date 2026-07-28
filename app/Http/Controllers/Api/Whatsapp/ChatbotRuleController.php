<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappChatbotRule;
use Illuminate\Http\Request;

class ChatbotRuleController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', WhatsappChatbotRule::class);

        return WhatsappChatbotRule::query()->latest()->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappChatbotRule::class);

        return response()->json(WhatsappChatbotRule::create($this->validated($request)), 201);
    }

    public function update(Request $request, WhatsappChatbotRule $rule)
    {
        $this->authorize('update', $rule);

        $rule->update($this->validated($request));

        return $rule;
    }

    public function destroy(WhatsappChatbotRule $rule)
    {
        $this->authorize('delete', $rule);

        $rule->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
            'enabled' => ['boolean'],
            'target' => ['required', 'in:all,individual,group'],
            'match_type' => ['required', 'in:contains,exact'],
            'name' => ['required', 'string', 'max:255'],
            'keywords' => ['required', 'array', 'min:1'],
            'keywords.*' => ['string', 'max:255'],
            'message_type' => ['required', 'in:text,media'],
            'body' => ['required_if:message_type,text', 'nullable', 'string', 'max:4096'],
            'media_url' => ['required_if:message_type,media', 'nullable', 'string', 'url'],
        ]);
    }
}
