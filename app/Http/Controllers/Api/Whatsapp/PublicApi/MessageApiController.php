<?php

namespace App\Http\Controllers\Api\Whatsapp\PublicApi;

use App\Http\Controllers\Controller;
use App\Models\WhatsappTemplate;
use App\Rules\ValidMobileNumber;
use App\Services\Whatsapp\BridgeClient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Public REST API — Send Direct Message API (screenshots 40-48). Same credit
 * check-and-decrement as every other send path in this app
 * (MessageController::store, SendCampaignMessageJob, SendAutoReplyJob) — an
 * external caller sending messages is no different from a user doing it
 * through the UI as far as the plan's WhatsApp credit limit is concerned.
 * sendAndDecrement() itself lives in SendsWhatsappViaApi, shared with
 * GroupApiController.
 */
class MessageApiController extends Controller
{
    use ResolvesPublicApiChannel, SendsWhatsappViaApi;

    public function __construct(private readonly BridgeClient $bridge) {}

    public function send(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'messages');

        $data = $request->validate([
            'number' => ['required', new ValidMobileNumber],
            'type' => ['required', 'in:text,media'],
            'message' => ['nullable', 'string', 'max:4096'],
            'media_url' => ['required_if:type,media', 'nullable', 'string', 'url'],
        ]);

        $result = $this->sendAndDecrement(
            $channel, $data['number'], $data['type'], $data['message'] ?? null,
            $data['media_url'] ?? null, null, null,
        );

        return response()->json($result, 201);
    }

    /**
     * Inline button/list body — uses this project's own interactive_config
     * shape (already established internally: {buttons:[...]} / {button_text,
     * sections}) rather than copying the reference's more complex nested
     * templateButtons/quickReplyButton structure, matching this module's
     * existing "own convention, not the exact reference shape" precedent.
     */
    public function sendTemplate(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'messages');

        $data = $request->validate([
            'number' => ['required', new ValidMobileNumber],
            'type' => ['required', 'in:buttons,list'],
            'message' => ['required', 'string', 'max:4096'],
            'config' => ['required', 'array'],
            'config.buttons' => ['required_if:type,buttons', 'array', 'max:3'],
            'config.buttons.*' => ['string', 'max:20'],
            'config.button_text' => ['required_if:type,list', 'string', 'max:20'],
            'config.sections' => ['required_if:type,list', 'array', 'min:1'],
        ]);

        $result = $this->sendAndDecrement(
            $channel, $data['number'], $data['type'], $data['message'], null, null, $data['config'],
        );

        return response()->json($result, 201);
    }

    /**
     * Sends a saved template by ID — reuses WhatsappTemplate's own
     * interactiveKind()/buildInteractiveConfig()/mediaKind(), the exact same
     * resolution CampaignController::store() already relies on.
     */
    public function sendByTemplate(Request $request)
    {
        $channel = $this->resolveChannel($request);
        $this->ensureGroupEnabled($channel, 'messages');

        $data = $request->validate([
            'number' => ['required', new ValidMobileNumber],
            'template_type' => ['required', Rule::in(['button', 'list', 'poll'])],
            'template_id' => ['required', 'integer', 'exists:whatsapp_templates,id'],
        ]);

        $template = WhatsappTemplate::withoutGlobalScopes()
            ->where('id', $data['template_id'])
            ->where('account_id', $channel->account_id)
            ->first();

        if (! $template) {
            throw new HttpException(404, 'Template not found for this account.');
        }

        if (! in_array($template->type, WhatsappTemplate::ALL_SENDABLE_TYPES, true)) {
            throw ValidationException::withMessages(['template_id' => ['This template type cannot be sent yet.']]);
        }

        $type = $template->type;
        $body = $template->body;
        $mediaUrl = null;
        $mediaType = null;
        $interactiveConfig = null;

        if ($interactiveKind = $template->interactiveKind()) {
            $type = $interactiveKind;
            $interactiveConfig = $template->buildInteractiveConfig();
        } else {
            $mediaUrl = $template->media_url;
            $mediaType = $template->mediaKind();
            $type = $mediaType ? 'media' : 'text';
        }

        $result = $this->sendAndDecrement($channel, $data['number'], $type, $body, $mediaUrl, $mediaType, $interactiveConfig);

        return response()->json($result, 201);
    }
}
