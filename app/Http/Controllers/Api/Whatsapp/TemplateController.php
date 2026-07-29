<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', WhatsappTemplate::class);

        return WhatsappTemplate::query()->latest()->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappTemplate::class);

        return response()->json(WhatsappTemplate::create($this->validated($request)), 201);
    }

    public function update(Request $request, WhatsappTemplate $template)
    {
        $this->authorize('update', $template);

        $template->update($this->validated($request));

        return $template;
    }

    public function destroy(WhatsappTemplate $template)
    {
        $this->authorize('delete', $template);

        $template->delete();

        return response()->noContent();
    }

    /**
     * Per-type character/count limits mirroring Meta's own WhatsApp message
     * limits, so a template can't be saved in a shape WhatsApp would refuse
     * to render: header 60, interactive body 1024 (plain text 4096, poll
     * question 255, media caption 1024), footer 60, button labels 20 (max 3),
     * list button 20 / section title 24 / row title 24 / row description 72
     * (max 10 sections, 10 rows total), poll options 100 chars (2-12),
     * carousel max 10 cards / card body 160 / 2 buttons per card.
     */
    private function validated(Request $request): array
    {
        $type = $request->input('type');

        $bodyMax = match ($type) {
            WhatsappTemplate::TYPE_TEXT => 4096,
            WhatsappTemplate::TYPE_TEXT_POLL => 255,
            default => 1024,
        };

        $bodyRequired = in_array($type, [
            WhatsappTemplate::TYPE_TEXT,
            WhatsappTemplate::TYPE_TEXT_BUTTONS,
            WhatsappTemplate::TYPE_TEXT_LISTS,
            WhatsappTemplate::TYPE_TEXT_POLL,
            WhatsappTemplate::TYPE_INTERACTIVE_BUTTONS,
        ], true);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(WhatsappTemplate::TYPES)],
            'body' => [$bodyRequired ? 'required' : 'nullable', 'string', "max:{$bodyMax}"],
            'footer' => ['nullable', 'string', 'max:60'],
            'media_url' => ['nullable', 'string', 'url'],
            'config' => ['nullable', 'array'],
        ];

        $rules += match ($type) {
            WhatsappTemplate::TYPE_TEXT_BUTTONS => [
                'config.buttons' => ['required', 'array', 'min:1', 'max:3'],
                'config.buttons.*' => ['required', 'string', 'max:20'],
            ],
            WhatsappTemplate::TYPE_INTERACTIVE_BUTTONS => [
                'config.buttons' => ['required', 'array', 'min:1', 'max:3'],
                'config.buttons.*.label' => ['required', 'string', 'max:20'],
                'config.header_type' => ['nullable', Rule::in(['none', 'text', 'image', 'video', 'document'])],
                'config.header_text' => ['nullable', 'string', 'max:60'],
            ],
            WhatsappTemplate::TYPE_TEXT_LISTS => [
                'config.button_text' => ['required', 'string', 'max:20'],
                'config.sections' => ['required', 'array', 'min:1', 'max:10'],
                'config.sections.*.title' => ['required', 'string', 'max:24'],
                'config.sections.*.rows' => ['required', 'array', 'min:1'],
                'config.sections.*.rows.*.title' => ['required', 'string', 'max:24'],
                'config.sections.*.rows.*.description' => ['nullable', 'string', 'max:72'],
                'config.sections.*.rows.*.id' => ['nullable', 'string', 'max:200'],
            ],
            WhatsappTemplate::TYPE_TEXT_POLL => [
                'config.poll_options' => ['required', 'array', 'min:2', 'max:12'],
                'config.poll_options.*' => ['required', 'string', 'max:100'],
            ],
            WhatsappTemplate::TYPE_TEXT_CAROUSEL => [
                'config.cards' => ['required', 'array', 'min:1', 'max:10'],
                'config.cards.*.title' => ['nullable', 'string', 'max:80'],
                'config.cards.*.body' => ['nullable', 'string', 'max:160'],
                'config.cards.*.buttons' => ['nullable', 'array', 'max:2'],
                'config.cards.*.buttons.*.label' => ['required', 'string', 'max:20'],
            ],
            default => [],
        };

        $validated = $request->validate($rules);

        // Rows are capped per-message, not per-section, so this can't be a
        // plain per-field rule.
        if ($type === WhatsappTemplate::TYPE_TEXT_LISTS) {
            $totalRows = collect($validated['config']['sections'])->sum(fn ($section) => count($section['rows']));

            if ($totalRows > 10) {
                throw ValidationException::withMessages([
                    'config.sections' => ['A list message can have at most 10 rows in total across all sections.'],
                ]);
            }
        }

        return $validated;
    }
}
