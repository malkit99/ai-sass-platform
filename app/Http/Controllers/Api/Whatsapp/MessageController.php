<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappCreditBalance;
use App\Models\WhatsappCreditLedger;
use App\Models\WhatsappContact;
use App\Models\WhatsappTemplate;
use App\Rules\ValidMobileNumber;
use App\Services\Whatsapp\BridgeClient;
use App\Services\Whatsapp\TemplateVariables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function __construct(private readonly BridgeClient $bridge) {}

    /**
     * Sends a single text/media message (the "Send Single Message" screen,
     * screenshot 31) — bulk campaigns get their own queued endpoint later
     * (Phase 1b, see 11-unofficial-whatsapp.md).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'phone' => ['required', new ValidMobileNumber],
            'type' => ['required', 'in:text,media,template'],
            'template_id' => ['required_if:type,template', 'nullable', 'integer', 'exists:whatsapp_templates,id'],
            'body' => ['required_if:type,text', 'nullable', 'string', 'max:4096'],
            'media_url' => ['required_if:type,media', 'nullable', 'string', 'url'],
            // Per-variable values typed into the form when the selected
            // template contains {{...}} placeholders.
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:500'],
        ]);

        $channel = Channel::findOrFail($data['channel_id']);
        $this->authorize('view', $channel);

        // A template is sent by reference, not by trusting whatever the
        // client echoed back — the template's own stored type/body/media_url
        // is authoritative, and its type tells the bridge exactly which
        // media kind to send (image/video/document/audio) instead of having
        // to guess one from the media_url's file extension.
        $mediaType = null;
        $interactiveConfig = null;

        if ($data['type'] === 'template') {
            $template = WhatsappTemplate::findOrFail($data['template_id']);

            if (! in_array($template->type, WhatsappTemplate::ALL_SENDABLE_TYPES, true)) {
                throw ValidationException::withMessages([
                    'template_id' => ['This template type cannot be sent yet.'],
                ]);
            }

            $data['body'] = $template->body;

            if ($interactiveKind = $template->interactiveKind()) {
                $data['type'] = $interactiveKind;
                $interactiveConfig = $template->buildInteractiveConfig();
            } else {
                $data['media_url'] = $template->media_url;
                $mediaType = $template->mediaKind();
                $data['type'] = $mediaType ? 'media' : 'text';
            }
        }

        // {{...}} placeholders resolve from (in priority order) the form's
        // typed variable values, then the phone's contact row if one exists
        // (name + CSV-imported params), then {{phone}} itself. Empty typed
        // values fall back rather than blanking a known contact value.
        $contact = WhatsappContact::where('phone', $data['phone'])->first();
        $typedVariables = array_filter($data['variables'] ?? [], fn ($value) => $value !== null && $value !== '');
        $data['body'] = TemplateVariables::render(
            $data['body'] ?? null,
            [...TemplateVariables::forContact($data['phone'], $contact), ...$typedVariables],
        );

        $account = Auth::user()->account;
        $balance = WhatsappCreditBalance::forAccount($account);

        if ($balance->credits_remaining < 1) {
            throw ValidationException::withMessages([
                'credits' => ['No WhatsApp credits remaining on your plan.'],
            ]);
        }

        // The DB column is only updated by webhook events pushed *from* the
        // bridge — a bridge crash/restart never fires one (there's no
        // graceful disconnect to react to), so it can silently drift from
        // reality. Checking live here catches exactly that case instead of
        // surfacing a raw "instance not connected" bridge exception.
        $liveStatus = $this->bridge->status($channel->id)['status'] ?? Channel::STATUS_DISCONNECTED;

        if ($liveStatus !== $channel->status) {
            $channel->update([
                'status' => $liveStatus,
                'connected_at' => $liveStatus === Channel::STATUS_CONNECTED ? $channel->connected_at : null,
            ]);
        }

        if ($liveStatus !== Channel::STATUS_CONNECTED) {
            throw ValidationException::withMessages([
                'channel_id' => ['This WhatsApp account is disconnected. Reconnect it from Accounts before sending.'],
            ]);
        }

        $conversation = Conversation::firstOrCreate(
            ['channel_id' => $channel->id, 'contact_phone' => $data['phone']],
            ['last_message_at' => now()]
        );

        $message = $conversation->messages()->create([
            'direction' => Message::DIRECTION_OUT,
            'type' => $data['type'],
            'body' => $data['body'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'status' => 'sending',
            'sent_by' => (string) Auth::id(),
        ]);

        try {
            $result = $this->bridge->sendMessage(
                $channel->id,
                $data['phone'],
                $data['type'],
                $data['body'] ?? null,
                $data['media_url'] ?? null,
                $mediaType,
                $interactiveConfig,
            );

            $message->update([
                'status' => 'sent',
                'external_id' => $result['message_id'] ?? null,
            ]);

            $conversation->update(['last_message_at' => now()]);

            WhatsappCreditLedger::record($account, -1, WhatsappCreditLedger::REASON_MESSAGE_SENT);
        } catch (\Throwable $e) {
            $message->update(['status' => 'failed', 'error' => $e->getMessage()]);

            throw ValidationException::withMessages([
                'message' => ['Failed to send message: '.$e->getMessage()],
            ]);
        }

        return response()->json($message->fresh(), 201);
    }
}
