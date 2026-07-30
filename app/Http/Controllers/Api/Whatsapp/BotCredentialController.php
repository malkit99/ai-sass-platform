<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappBotCredential;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AI provider connections for the Bot Builder's AI Reply node. api_key is
 * never returned (see WhatsappBotCredential::$hidden) — index() only ever
 * exposes id/provider/label, enough for the property panel's picker.
 */
class BotCredentialController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', WhatsappBotCredential::class);

        return WhatsappBotCredential::query()->latest()->get(['id', 'provider', 'label']);
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappBotCredential::class);

        $data = $request->validate([
            'provider' => ['required', Rule::in(WhatsappBotCredential::PROVIDERS)],
            'label' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:500'],
        ]);

        $credential = WhatsappBotCredential::create($data);

        return response()->json($credential->only(['id', 'provider', 'label']), 201);
    }

    public function destroy(WhatsappBotCredential $credential)
    {
        $this->authorize('delete', $credential);

        $credential->delete();

        return response()->noContent();
    }
}
