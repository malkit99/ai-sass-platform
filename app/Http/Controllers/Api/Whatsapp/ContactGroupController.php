<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappContactGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactGroupController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', WhatsappContactGroup::class);

        return WhatsappContactGroup::query()->withCount('contacts')->latest()->get();
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappContactGroup::class);

        $group = WhatsappContactGroup::create($this->validated($request));

        return response()->json($group->loadCount('contacts'), 201);
    }

    public function update(Request $request, WhatsappContactGroup $group)
    {
        $this->authorize('update', $group);

        $group->update($this->validated($request));

        return $group->loadCount('contacts');
    }

    public function destroy(WhatsappContactGroup $group)
    {
        $this->authorize('delete', $group);

        $group->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([WhatsappContactGroup::STATUS_ENABLE, WhatsappContactGroup::STATUS_DISABLE])],
        ]);
    }
}
