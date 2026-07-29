<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAutoresponder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        return response()->json(WhatsappAutoresponder::create($this->validated($request)), 201);
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

    /**
     * message_type only covers the three tabs the reference app's
     * Autoresponder screen actually shows (screenshot 77) — Text & Media,
     * Buttons, List messages. No Poll/Template tab here, unlike bulk
     * campaigns/single-send: an autoresponder reply is authored inline, not
     * picked from the saved template library.
     */
    private function validated(Request $request): array
    {
        $type = $request->input('message_type');

        $rules = [
            'channel_id' => ['nullable', 'integer', 'exists:channels,id'],
            'enabled' => ['boolean'],
            'target' => ['required', Rule::in([
                WhatsappAutoresponder::TARGET_ALL,
                WhatsappAutoresponder::TARGET_INDIVIDUAL,
                WhatsappAutoresponder::TARGET_GROUP,
            ])],
            'target_phone' => ['required_if:target,individual', 'nullable', 'string', 'max:20'],
            'contact_group_id' => ['required_if:target,group', 'nullable', 'integer', 'exists:whatsapp_contact_groups,id'],
            'message_type' => ['required', 'in:text,media,buttons,list'],
            'body' => ['required_if:message_type,text,buttons,list', 'nullable', 'string', 'max:4096'],
            'media_url' => ['required_if:message_type,media', 'nullable', 'string', 'url'],
            'resubmit_after_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'except_contacts' => ['nullable', 'array'],
            'except_contacts.*' => ['string', 'max:20'],
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

        return $data;
    }
}
