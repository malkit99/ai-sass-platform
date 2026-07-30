<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Http\Controllers\Controller;
use App\Models\WhatsappBotFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BotFlowController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', WhatsappBotFlow::class);

        return WhatsappBotFlow::query()->latest()->get();
    }

    public function dashboard()
    {
        $this->authorize('viewAny', WhatsappBotFlow::class);

        $totalRuns = (int) WhatsappBotFlow::sum('run_count');
        $totalCompletions = (int) WhatsappBotFlow::sum('completion_count');

        return response()->json([
            'total_bots' => WhatsappBotFlow::count(),
            'active_bots' => WhatsappBotFlow::where('status', WhatsappBotFlow::STATUS_ACTIVE)->count(),
            'total_runs' => $totalRuns,
            'completion_rate' => $totalRuns > 0 ? round($totalCompletions / $totalRuns * 100) : 0,
        ]);
    }

    /**
     * The flow editor (screenshots 59-62) is a dedicated full-screen route,
     * not nested inside WhatsappView — it navigates to a bot by id rather
     * than receiving it in-memory from the dashboard's list, so it needs
     * its own fetch-by-id endpoint.
     */
    public function show(WhatsappBotFlow $bot)
    {
        $this->authorize('view', $bot);

        return $bot;
    }

    public function store(Request $request)
    {
        $this->authorize('create', WhatsappBotFlow::class);

        $data = $this->validated($request);
        $data['source'] = $request->input('source', WhatsappBotFlow::SOURCE_SCRATCH);

        return response()->json(WhatsappBotFlow::create($data), 201);
    }

    public function update(Request $request, WhatsappBotFlow $bot)
    {
        $this->authorize('update', $bot);

        $bot->update($this->validated($request));

        return $bot;
    }

    public function destroy(WhatsappBotFlow $bot)
    {
        $this->authorize('delete', $bot);

        $bot->delete();

        return response()->noContent();
    }

    /**
     * "Import a file" (screenshot 58) — a previously exported bot's own
     * flow_definition, re-imported as a brand new bot (not overwriting
     * anything). Same shape validation as store(), just sourced from an
     * uploaded .json file instead of the request body directly.
     */
    public function import(Request $request)
    {
        $this->authorize('create', WhatsappBotFlow::class);

        $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'file' => ['required', 'file', 'mimes:json,txt', 'max:2048'],
        ]);

        $decoded = json_decode(file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($decoded) || ! isset($decoded['flow_definition']['nodes'], $decoded['flow_definition']['edges'])) {
            throw ValidationException::withMessages(['file' => ['This file is not a valid bot export.']]);
        }

        $bot = WhatsappBotFlow::create([
            'channel_id' => $request->integer('channel_id'),
            'name' => $decoded['name'] ?? 'Imported Bot',
            'trigger_keywords' => is_array($decoded['trigger_keywords'] ?? null) ? $decoded['trigger_keywords'] : [],
            'flow_definition' => $decoded['flow_definition'],
            'status' => WhatsappBotFlow::STATUS_DRAFT,
            'source' => WhatsappBotFlow::SOURCE_IMPORTED,
        ]);

        return response()->json($bot, 201);
    }

    /**
     * "Choose JSON File" / drag-drop export counterpart — downloads exactly
     * what import() expects back, so a bot can round-trip between
     * instances (or just serve as a backup).
     */
    public function export(WhatsappBotFlow $bot)
    {
        $this->authorize('view', $bot);

        return response()->streamDownload(function () use ($bot) {
            echo json_encode([
                'name' => $bot->name,
                'trigger_keywords' => $bot->trigger_keywords,
                'flow_definition' => $bot->flow_definition,
            ], JSON_PRETTY_PRINT);
        }, Str::slug($bot->name ?: 'bot').'.json');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in([WhatsappBotFlow::STATUS_DRAFT, WhatsappBotFlow::STATUS_ACTIVE])],
            // 'present' not 'required' — an empty array (no keywords yet, no
            // edges yet on a freshly-created single-node flow) is valid, it's
            // only a *missing key* that isn't.
            'trigger_keywords' => ['present', 'array'],
            'trigger_keywords.*' => ['string', 'max:255'],
            'flow_definition' => ['required', 'array'],
            'flow_definition.nodes' => ['present', 'array'],
            'flow_definition.edges' => ['present', 'array'],
        ]);
    }
}
