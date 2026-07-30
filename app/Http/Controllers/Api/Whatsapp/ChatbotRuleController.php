<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappChatbotRule;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /**
     * message_type covers all four tabs the reference app's Chatbot item
     * form shows (screenshot 89): Text & Media, Buttons, List messages, and
     * Templates — unlike the Autoresponder, which has no Templates tab. A
     * 'template' pick is resolved into a plain text/media/buttons/list
     * snapshot below (same pattern as CampaignController::store()) so the
     * saved rule stays self-contained even if the template is later edited
     * or deleted.
     */
    private function validated(Request $request): array
    {
        $type = $request->input('message_type');

        $rules = [
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
            'enabled' => ['boolean'],
            'target' => ['required', Rule::in([
                WhatsappChatbotRule::TARGET_ALL,
                WhatsappChatbotRule::TARGET_INDIVIDUAL,
                WhatsappChatbotRule::TARGET_GROUP,
            ])],
            'target_phone' => ['required_if:target,individual', 'nullable', 'string', 'max:20'],
            'contact_group_id' => ['required_if:target,group', 'nullable', 'integer', 'exists:whatsapp_contact_groups,id'],
            'match_type' => ['required', 'in:contains,exact'],
            'name' => ['required', 'string', 'max:255'],
            'keywords' => ['required', 'array', 'min:1'],
            'keywords.*' => ['string', 'max:255'],
            'message_type' => ['required', 'in:text,media,buttons,list,template'],
            'template_id' => ['required_if:message_type,template', 'nullable', 'integer', 'exists:whatsapp_templates,id'],
            'body' => ['required_if:message_type,text,buttons,list', 'nullable', 'string', 'max:4096'],
            'media_url' => ['required_if:message_type,media', 'nullable', 'string', 'url'],
        ];

        $rules += match ($type) {
            'buttons' => [
                'config.buttons' => ['required', 'array', 'min:1', 'max:3'],
                'config.buttons.*' => ['required', 'string', 'max:20'],
            ],
            'list' => [
                'config.button_text' => ['required', 'string', 'max:20'],
                'config.sections' => ['required', 'array', 'min:1', 'max:10'],
                'config.sections.*.title' => ['required', 'string', 'max:24'],
                'config.sections.*.rows' => ['required', 'array', 'min:1'],
                'config.sections.*.rows.*.title' => ['required', 'string', 'max:24'],
                'config.sections.*.rows.*.description' => ['nullable', 'string', 'max:72'],
                'config.sections.*.rows.*.id' => ['nullable', 'string', 'max:200'],
            ],
            default => [],
        };

        $data = $request->validate($rules);

        if (isset($data['config'])) {
            $data['interactive_config'] = $data['config'];
            unset($data['config']);
        }

        if ($data['message_type'] === 'template') {
            $template = WhatsappTemplate::findOrFail($data['template_id']);

            if (! in_array($template->type, WhatsappTemplate::ALL_SENDABLE_TYPES, true)) {
                throw ValidationException::withMessages([
                    'template_id' => ['This template type can\'t be used in a chatbot reply yet.'],
                ]);
            }

            $data['body'] = $template->body;

            if ($interactiveKind = $template->interactiveKind()) {
                $data['message_type'] = $interactiveKind;
                $data['interactive_config'] = $template->buildInteractiveConfig();
            } else {
                $data['media_url'] = $template->media_url;
                $mediaType = $template->mediaKind();
                $data['message_type'] = $mediaType ? 'media' : 'text';
            }
        }

        unset($data['template_id']);

        return $data;
    }
}
