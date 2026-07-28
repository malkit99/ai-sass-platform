<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Rules\ValidMobileNumber;
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
            'phone' => ['nullable', new ValidMobileNumber],
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

        ActivityLog::record($lead, 'created', "{$lead->name} was added as a new lead.");

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
            'phone' => ['sometimes', 'nullable', new ValidMobileNumber],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'stage_id' => ['sometimes', 'integer', 'exists:pipeline_stages,id'],
            'is_hot' => ['sometimes', 'boolean'],
        ]);

        $data['last_activity_at'] = now();

        $lead->update($data);

        $this->logUpdate($lead);

        return $lead;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lead $lead)
    {
        $this->authorize('delete', $lead);

        // Logged before the delete — the log entry stores its own description
        // string precisely so it survives after the lead row is gone.
        ActivityLog::record($lead, 'deleted', "{$lead->name} was deleted.");

        $lead->delete();

        return response()->noContent();
    }

    /**
     * Records one activity entry describing what actually changed — the
     * stage move and the hot toggle are the interesting, frequent cases and
     * get their own readable message; anything else falls back to a generic
     * "details updated" entry.
     */
    private function logUpdate(Lead $lead): void
    {
        if ($lead->wasChanged('stage_id')) {
            ActivityLog::record($lead, 'stage_changed', "{$lead->name} moved to {$lead->stage->name}.");

            return;
        }

        if ($lead->wasChanged('is_hot')) {
            $state = $lead->is_hot ? 'marked as hot' : 'unmarked as hot';
            ActivityLog::record($lead, 'updated', "{$lead->name} was {$state}.");

            return;
        }

        if ($lead->wasChanged()) {
            ActivityLog::record($lead, 'updated', "{$lead->name}'s details were updated.");
        }
    }
}
