<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Pipeline;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::query()->with('labels', 'deals');

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', $request->integer('pipeline_id'));
        }

        if ($request->boolean('hot')) {
            $query->where('is_hot', true);
        }

        return $query->latest('last_activity_at')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Lead::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'pipeline_id' => ['nullable', 'integer', 'exists:pipelines,id'],
            'stage_id' => ['nullable', 'integer', 'exists:pipeline_stages,id'],
        ]);

        $pipeline = $data['pipeline_id'] ?? null
            ? Pipeline::findOrFail($data['pipeline_id'])
            : Pipeline::where('is_default', true)->firstOrFail();

        $stageId = $data['stage_id'] ?? $pipeline->stages()->orderBy('order')->value('id');

        $lead = Lead::create([
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stageId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'description' => $data['description'] ?? null,
            'source' => 'manual',
            'last_activity_at' => now(),
        ]);

        return response()->json($lead, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);

        return $lead->load('labels', 'deals');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'stage_id' => ['sometimes', 'integer', 'exists:pipeline_stages,id'],
            'is_hot' => ['sometimes', 'boolean'],
        ]);

        $data['last_activity_at'] = now();

        $lead->update($data);

        return $lead;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        return response()->noContent();
    }
}
