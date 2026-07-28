<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(WhatsappTemplate::TYPES)],
            'body' => ['nullable', 'string', 'max:4096'],
            'footer' => ['nullable', 'string', 'max:60'],
            'media_url' => ['nullable', 'string', 'url'],
            'config' => ['nullable', 'array'],
        ]);
    }
}
