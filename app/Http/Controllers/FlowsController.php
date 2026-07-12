<?php

namespace App\Http\Controllers;

use App\Models\Flow;
use App\Models\FlowConnectedDevice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * /flows + /flows/builder + /flows/api/*.
 *
 * Adapted from the old project's split design:
 *   - D:\wadesk_2806\New folder\app\Http\Controllers\FlowController.php       (web)
 *   - D:\wadesk_2806\New folder\app\Http\Controllers\Api\Main\FlowController.php (api)
 *
 * Folded into one controller because the new project's React builder
 * is a single SPA mount and we don't need the legacy /admin/* split.
 *
 * Encryption + LogsNotifications happen at the model layer (Flow casts
 * + LogsNotifications trait), so this controller stays thin.
 */
class FlowsController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $status = $request->string('status')->toString() ?: 'all';   // all / live / paused / draft
        $cat    = $request->string('category')->toString() ?: 'all';
        $q      = $request->string('q')->toString();

        $flows = Flow::query()
            ->forCurrentWorkspace()
            // NOTE: intentionally NOT ->forCurrentEngine() here. The KPI cards +
            // status/category counts + the "most-used" card are workspace-scoped
            // only, so engine-filtering the list made it disagree with them —
            // flows whose `provider` is NULL (seeded/imported) or set to a
            // non-active engine vanished, producing "5 flows / No data found".
            // A flow is workspace automation; the list shows every flow.
            ->withCount('activeDevices')
            ->withCount(['subscribers as active_subscriber_count' => fn ($q) => $q->where('status', 'active')])
            ->withCount(['subscribers as completed_subscriber_count' => fn ($q) => $q->where('status', 'completed')])
            ->withCount(['subscribers as failed_subscriber_count' => fn ($q) => $q->where('status', 'failed')])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(function (Flow $f) use ($status, $cat, $q) {
                $state = $f->is_published ? ($f->is_active ? 'live' : 'paused') : 'draft';
                if ($status !== 'all' && $state !== $status) return false;
                if ($cat !== 'all' && (string) $f->category !== $cat) return false;
                if ($q !== '' && !str_contains(mb_strtolower((string) $f->flow_name), mb_strtolower($q))) return false;
                return true;
            })
            ->values();
        $flows = $this->paginateCollection($flows, $request, 12);

        $statusCounts   = $this->statusCounts($userId);
        $categoryCounts = $this->categoryCounts($userId);
        $featured       = $this->mostUsedFlow($userId);
        $featuredView   = $featured ? view('user.flows._featured', compact('featured'))->render() : '';

        if ($request->boolean('partial')) {
            return response()->json([
                'ok'             => true,
                'cards'          => view('user.flows._cards', compact('flows'))->render(),
                'featured'       => $featuredView,
                'statusCounts'   => $statusCounts,
                'categoryCounts' => $categoryCounts,
                'pagination'     => view('user.partials.pagination', ['paginator' => $flows, 'dataAttr' => 'data-fl-page', 'label' => 'flows'])->render(),
                'shown'          => $flows->count(),
                'total'          => $flows->total(),
                'page'           => $flows->currentPage(),
            ]);
        }

        return view('user.flows.index', [
            'flows'           => $flows,
            'featured'        => $featured,
            'statusCounts'    => $statusCounts,
            'categoryCounts'  => $categoryCounts,
            'currentStatus'   => $status,
            'currentCategory' => $cat,
            'currentQuery'    => $q,
            // Admin-curated starter templates → "Start from a template" gallery.
            'flowTemplates'   => \App\Models\FlowTemplate::active()->ordered()->get(),
        ]);
    }

    public function builder(Request $request, ?int $id = null): View
    {
        $flow = null;
        if ($id) {
            $flow = Flow::query()->forCurrentWorkspace()->find($id);
        }
        // New flow: ?type=call opens the call-flow palette; an existing flow
        // uses its stored flow_type.
        $flowType = $flow
            ? ($flow->flow_type ?: 'chat')
            : (in_array($request->string('type')->toString(), ['call', 'instagram'], true) ? $request->string('type')->toString() : 'chat');
        return view('user.flows.builder', [
            'flow'      => $flow,
            'flowType'  => $flowType,
            'flowJson'  => $flow ? $flow->decoded_flow_data : ['flowNodes' => [], 'flowEdges' => []],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->findOrFail($id);
        $flow->forceDelete(); // permanent delete — remove the row from `flows` (not soft-delete)
        return response()->json(['ok' => true]);
    }

    public function duplicate(int $id): RedirectResponse
    {
        $original = Flow::query()->forCurrentWorkspace()->findOrFail($id);
        $copy = $original->replicate(['published_at']);
        $copy->flow_name    = $original->flow_name . ' (Copy)';
        $copy->is_published = false;
        $copy->published_at = null;
        $copy->save();
        if ($original->flow_file_path) {
            $copy->saveFlowFile($original->decoded_flow_data);
        }
        return redirect()->route('user.flows.builder', ['id' => $copy->id])
            ->with('status', 'Flow duplicated.');
    }

    public function toggle(int $id): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->findOrFail($id);
        $flow->update(['is_active' => !$flow->is_active]);
        return response()->json(['ok' => true, 'is_active' => $flow->is_active]);
    }

    /* =========================================================
     * Export / Import / Clone-from-template.
     * ========================================================= */

    /**
     * GET /flows/{id}/export — download this flow as a portable JSON file.
     * Strips workspace/user/ids so it imports into any workspace (or gets
     * uploaded to the admin panel as a template). Same shape import() reads.
     */
    public function export(int $id)
    {
        $flow = Flow::query()->forCurrentWorkspace()->findOrFail($id);
        $payload = [
            '_wadesk_flow_export' => 1,
            'exported_at'         => now()->toIso8601String(),
            'name'                => (string) $flow->flow_name,
            'flow_type'           => $flow->flow_type ?: 'chat',
            'category'            => $flow->category,
            'flow_data'           => $flow->decoded_flow_data ?: ['flowNodes' => [], 'flowEdges' => []],
        ];
        $slug = \Illuminate\Support\Str::slug((string) $flow->flow_name) ?: ('flow-' . $flow->id);
        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="' . $slug . '.wadesk-flow.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /flows/import — create a new flow in the current workspace from an
     * uploaded export JSON (from export() above, or an admin template download).
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:4096|mimetypes:application/json,text/plain,text/json',
        ]);

        $raw  = @file_get_contents($request->file('file')->getRealPath());
        $json = json_decode((string) $raw, true);

        [$flowData, $meta] = $this->parseImportPayload($json);
        if ($flowData === null) {
            return back()->withErrors(['file' => 'This is not a valid WaDesk flow export.']);
        }

        $wsId = (int) $request->user()->current_workspace_id;
        try {
            \App\Services\PlanLimitGuard::check(
                $request->user()->currentWorkspace,
                'flow_limit',
                Flow::where('workspace_id', $wsId)->count(),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $flow = Flow::create([
            'user_id'      => Auth::id(),
            'workspace_id' => $wsId,
            'flow_name'    => ($meta['name'] ?: 'Imported flow') . ' (imported)',
            'flow_data'    => json_encode($flowData),
            'flow_type'    => in_array($meta['flow_type'], ['chat', 'call', 'instagram'], true) ? $meta['flow_type'] : 'chat',
            'category'     => $meta['category'],
            'is_published' => false,
            'is_active'    => true,
        ] + $this->extractTriggerColumns($flowData));
        $flow->saveFlowFile($flowData);

        return redirect()->route('user.flows.builder', ['id' => $flow->id])
            ->with('status', 'Flow imported — review it, then Publish when ready.');
    }

    /**
     * POST /flows/templates/{id}/clone — clone an admin template into this
     * workspace as a fresh (unpublished) flow, ready to customise.
     */
    public function cloneTemplate(int $id, Request $request): RedirectResponse
    {
        $tpl  = \App\Models\FlowTemplate::active()->findOrFail($id);
        $wsId = (int) $request->user()->current_workspace_id;
        try {
            \App\Services\PlanLimitGuard::check(
                $request->user()->currentWorkspace,
                'flow_limit',
                Flow::where('workspace_id', $wsId)->count(),
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['template' => $e->getMessage()]);
        }

        $flowData = is_array($tpl->flow_data) ? $tpl->flow_data : ['flowNodes' => [], 'flowEdges' => []];
        $flow = Flow::create([
            'user_id'      => Auth::id(),
            'workspace_id' => $wsId,
            'flow_name'    => $tpl->name,
            'flow_data'    => json_encode($flowData),
            'flow_type'    => in_array($tpl->flow_type, ['chat', 'call', 'instagram'], true) ? $tpl->flow_type : 'chat',
            'category'     => $tpl->category,
            'is_published' => false,
            'is_active'    => true,
        ] + $this->extractTriggerColumns($flowData));
        $flow->saveFlowFile($flowData);
        $tpl->increment('clone_count');

        return redirect()->route('user.flows.builder', ['id' => $flow->id])
            ->with('status', 'Template "' . $tpl->name . '" cloned — customise it, then Publish.');
    }

    /**
     * Pull flow_data + metadata out of an uploaded export. Accepts the canonical
     * {_wadesk_flow_export, name, flow_type, category, flow_data} wrapper AND a
     * bare {flowNodes, flowEdges} graph. Returns [array|null $flowData, array $meta].
     */
    private function parseImportPayload($json): array
    {
        $meta = ['name' => 'Imported flow', 'flow_type' => 'chat', 'category' => null];
        if (!is_array($json)) return [null, $meta];

        if (isset($json['flow_data']) && is_array($json['flow_data'])) {
            $fd = $json['flow_data'];
            $meta['name']      = trim((string) ($json['name'] ?? '')) ?: 'Imported flow';
            $meta['flow_type'] = (string) ($json['flow_type'] ?? 'chat');
            $meta['category']  = $json['category'] ?? null;
        } elseif (isset($json['flowNodes'])) {
            $fd = $json;
        } else {
            return [null, $meta];
        }

        if (!isset($fd['flowNodes']) || !is_array($fd['flowNodes'])) return [null, $meta];
        $flowData = [
            'flowNodes' => array_values($fd['flowNodes']),
            'flowEdges' => array_values($fd['flowEdges'] ?? []),
            'vars'      => is_array($fd['vars'] ?? null) ? $fd['vars'] : [],
        ];
        return [$flowData, $meta];
    }

    /* =========================================================
     * API endpoints — used by the React builder.
     * ========================================================= */

    public function apiSave(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
        }
        $userId = Auth::id();
        $validator = Validator::make($request->all(), [
            'flow_name' => 'required|string|max:255',
            'flow_data' => 'required|array',
            'flow_id'   => 'nullable|integer',
            'category'  => 'nullable|string|max:64',
            'flow_type' => 'nullable|in:chat,call,instagram',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $flowData = $this->normalizeMediaUrls($request->input('flow_data'));
            // Sync the trigger node's audience config from flow_data onto
            // the flows table columns so Laravel can query "which flows
            // want this tag / group" without decrypting flow_data.
            $triggerCols = $this->extractTriggerColumns($flowData);

            if ($request->filled('flow_id')) {
                $flow = Flow::query()->forCurrentWorkspace()->find($request->integer('flow_id'));
                if (!$flow) {
                    return response()->json(['success' => false, 'message' => 'Flow not found'], 404);
                }
                $flow->fill([
                    'flow_name' => $request->string('flow_name')->toString(),
                    'flow_data' => json_encode($flowData),
                    'category'  => $request->string('category')->toString() ?: $flow->category,
                    'flow_type' => $request->string('flow_type')->toString() ?: ($flow->flow_type ?: 'chat'),
                ] + $triggerCols)->save();
                Log::info('Flow updated', ['flow_id' => $flow->id]);
            } else {
                // Plan limit — create only, edits don't count toward the cap.
                // Plan limit per-workspace, not aggregate per-user.
                $wsId = (int) $request->user()->current_workspace_id;
                \App\Services\PlanLimitGuard::check(
                    $request->user()->currentWorkspace,
                    'flow_limit',
                    Flow::where('workspace_id', $wsId)->count(),
                );
                $flow = Flow::create([
                    'user_id'      => $userId,
                    'workspace_id' => $wsId,
                    'flow_name'    => $request->string('flow_name')->toString(),
                    'flow_data'    => json_encode($flowData),
                    'category'     => $request->string('category')->toString() ?: null,
                    'flow_type'    => $request->string('flow_type')->toString() ?: 'chat',
                    'is_published' => false,
                    'is_active'    => true,
                ] + $triggerCols);
                Log::info('Flow created', ['flow_id' => $flow->id, 'trigger' => $triggerCols]);
            }

            $filePath = $flow->saveFlowFile($flowData);

            return response()->json([
                'success' => true,
                'message' => 'Flow saved successfully',
                'data'    => [
                    'flow_id'        => $flow->id,
                    'flow_name'      => $flow->flow_name,
                    'flow_file_path' => $filePath,
                    'created_at'     => $flow->created_at,
                    'updated_at'     => $flow->updated_at,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('FLOW SAVE FAILED', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error saving flow: ' . $e->getMessage()], 500);
        }
    }

    public function apiPublish(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['flow_id' => 'required|integer']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $flow = Flow::query()->forCurrentWorkspace()->findOrFail($request->integer('flow_id'));
        $flow->update(['is_published' => true, 'published_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Flow published', 'data' => $flow]);
    }

    public function apiUnpublish(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['flow_id' => 'required|integer']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $flow = Flow::query()->forCurrentWorkspace()->findOrFail($request->integer('flow_id'));
        $flow->update(['is_published' => false, 'published_at' => null]);
        return response()->json(['success' => true, 'message' => 'Flow unpublished', 'data' => $flow]);
    }

    public function apiIndex(): JsonResponse
    {
        $flows = Flow::query()->forCurrentWorkspace()->orderByDesc('updated_at')->get();
        return response()->json(['success' => true, 'data' => $flows]);
    }

    public function apiShow(int $id): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->with('connectedDevices')->find($id);
        if (!$flow) return response()->json(['success' => false, 'message' => 'Flow not found'], 404);
        return response()->json([
            'success' => true,
            'data'    => [
                'flow'      => $flow,
                'flow_data' => $flow->decoded_flow_data,
            ],
        ]);
    }

    /**
     * Node-runtime-facing flow fetch. Lives at /api/flows/{id} (top
     * level, no workspace.role middleware) because the Node bot fetches
     * it without a session. Normalizes React-builder shape to the
     * PascalCase format Node's executeFlowNode expects.
     */
    public function nodeShow(Request $request, int $id): JsonResponse
    {
        // X-Node-Token gate — flows contain the full automation logic
        // (prompts, AI-key references, business rules). Without this
        // anyone could enumerate every tenant's flow by id.
        $expected = node_token();
        $token    = (string) $request->header('X-Node-Token', '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            return response()->json(['success' => false, 'message' => 'unauthorized'], 401);
        }

        $flow = Flow::query()->find($id);
        if (!$flow) return response()->json(['success' => false, 'message' => 'Flow not found'], 404);

        $raw = $flow->decoded_flow_data ?? [];
        $normalized = app(\App\Services\Flows\FlowNormalizer::class)->normalize($raw);

        // Flows carry workspace_id directly now. Surface it on the
        // Node payload so /api/appointments/slots + /book callbacks
        // include it without needing to round-trip through User.
        $normalized['workspace_id'] = $flow->workspace_id
            ?: \App\Models\User::find($flow->user_id)?->current_workspace_id;

        return response()->json([
            'success' => true,
            'data'    => [
                'flow'      => $flow->only(['id', 'flow_name', 'user_id', 'is_published']),
                'flow_data' => $normalized,
            ],
        ]);
    }

    public function apiDestroy(int $id): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->find($id);
        if (!$flow) return response()->json(['success' => false, 'message' => 'Flow not found'], 404);
        $flow->forceDelete(); // permanent delete — remove the row from `flows` (not soft-delete)
        return response()->json(['success' => true, 'message' => 'Flow deleted']);
    }

    public function apiUploadMedia(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200',
            'type' => 'required|in:image,video,audio,document',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        try {
            $file = $request->file('file');
            // Secure-upload guard: strict extension + real-MIME allowlist and a
            // server-controlled, randomised filename. Never trust the client
            // extension — otherwise x.php lands in the web root => RCE.
            if ($problem = \App\Support\SecureUpload::problem($file)) {
                return response()->json(['success' => false, 'errors' => ['file' => [$problem]]], 422);
            }
            $type = $request->string('type')->toString();
            $uploadDir  = 'uploads/flows/' . $type . 's';
            $publicPath = public_path($uploadDir);
            if (!is_dir($publicPath)) @mkdir($publicPath, 0755, true);
            $filename = \App\Support\SecureUpload::safeName($file);
            $file->move($publicPath, $filename);
            return response()->json([
                'success' => true,
                'data' => [
                    'url'      => url($uploadDir . '/' . $filename),
                    'filename' => $filename,
                    'type'     => $type,
                    'mimeType' => $file->getClientMimeType(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Which AI text-generation models are available to the workspace.
     * Returns only providers the admin has switched on (admin_ai_keys
     * is_active = true), keyed by provider with the admin's default
     * model surfaced. Drives the AI assistant node's Model dropdown
     * so users never pick a model the bridge can't actually call.
     */
    public function apiAiModels(): JsonResponse
    {
        $rows = \DB::table('admin_ai_keys')
            ->where('is_active', true)
            // ElevenLabs is voice-only — exclude from text-model picker.
            ->whereNotIn('provider', ['elevenlabs'])
            ->orderBy('sort_order')
            ->get(['provider', 'name', 'default_model', 'extra_config']);

        // DIAGNOSTIC — reveals which admin keys are ACTIVE + their default
        // models, so "key active but no models in flow" can be pinned to data
        // (0 active rows / no default_model) vs a front-end fetch issue.
        \Illuminate\Support\Facades\Log::info('[AI-MODELS] admin keys', [
            'active_rows' => $rows->count(),
            'providers'   => $rows->map(fn ($r) => $r->provider . '=' . ($r->default_model ?: 'NO_DEFAULT'))->all(),
        ]);

        $providerLabel = [
            'openai'    => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini'    => 'Google',
            'mistral'   => 'Mistral',
        ];

        $models = [];
        foreach ($rows as $r) {
            $label = $providerLabel[$r->provider] ?? ucfirst($r->provider);
            $default = (string) ($r->default_model ?? '');
            if ($default === '') continue;
            $extra = json_decode((string) ($r->extra_config ?? '[]'), true) ?: [];
            // Admin can list extra model ids in extra_config.models — we
            // surface those too so e.g. they can offer gpt-4o + gpt-4o-mini
            // from the same provider key. Default model always comes first.
            $extraModels = is_array($extra['models'] ?? null) ? $extra['models'] : [];
            $list = array_values(array_unique(array_merge([$default], $extraModels)));
            foreach ($list as $m) {
                $models[] = [
                    'value'    => $m,
                    'label'    => $label . ' · ' . $m,
                    'provider' => $r->provider,
                ];
            }
        }

        // BYOK — surface the workspace's OWN provider keys so the customer can
        // pick a model backed by their key, even when the admin hasn't enabled
        // that provider globally. Runtime already honours BYOK via
        // AiKeyResolver (workspace key → admin fallback); this just lets the
        // node's Model dropdown show it as selectable instead of "not enabled".
        // BYOK keys ALWAYS appear in the picker now (owner decision) — any
        // active AiProviderKey the workspace saved becomes selectable here,
        // regardless of plan flag, so "user adds their key → it shows in flows".
        $workspace = Auth::user()?->current_workspace_id
            ? \App\Models\Workspace::find(Auth::user()->current_workspace_id)
            : null;
        if ($workspace) {
            $byokDefaults = [
                'openai'    => ['gpt-4o-mini', 'gpt-4o'],
                'anthropic' => ['claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
                'gemini'    => ['gemini-2.0-flash', 'gemini-1.5-pro'],
                'mistral'   => ['mistral-large-latest', 'mistral-small-latest'],
            ];
            $own = \App\Models\AiProviderKey::query()
                ->where('workspace_id', $workspace->id)
                ->where('is_active', true)
                ->pluck('provider')
                ->all();
            foreach ($own as $prov) {
                // Workspace has its OWN key for this provider → drop the admin's
                // models for it so ONLY the user's key shows (not both).
                $models = array_values(array_filter($models, fn ($mm) => $mm['provider'] !== $prov));
                $label = $providerLabel[$prov] ?? ucfirst($prov);
                foreach (($byokDefaults[$prov] ?? []) as $m) {
                    $models[] = [
                        'value'    => $m,
                        'label'    => $label . ' (your key) · ' . $m,
                        'provider' => $prov,
                    ];
                }
            }
        }

        \Illuminate\Support\Facades\Log::info('[AI-MODELS] returning', ['count' => count($models)]);
        return response()->json(['ok' => true, 'models' => $models]);
    }

    /**
     * GET /flows/api/ai-assistants
     * Trained chat assistants (from /ai-training) for the current
     * workspace, so the flow's AI node can attach one and pull its
     * knowledge base into the reply. `sources` = count of READY
     * training rows that apply (assistant-scoped + workspace-wide) so
     * the builder can warn when an assistant has nothing trained yet.
     */
    public function apiAiAssistants(): JsonResponse
    {
        $wsId = (int) (auth()->user()?->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => true, 'assistants' => []]);

        $rows = \App\Models\AiChatAssistant::where('workspace_id', $wsId)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $assistants = $rows->map(fn ($a) => [
            'id'      => (int) $a->id,
            'name'    => (string) $a->name,
            'status'  => (string) $a->status,
            'sources' => \App\Models\AiTrainingSource::where('workspace_id', $wsId)
                ->where(fn ($q) => $q->whereNull('assistant_id')->orWhere('assistant_id', $a->id))
                ->where('status', 'ready')->count(),
        ])->values();

        return response()->json(['ok' => true, 'assistants' => $assistants]);
    }

    /**
     * POST /flows/api/ai-generate
     * Take a natural-language prompt + admin-enabled model, ask the LLM
     * to emit a flow JSON in the React-builder shape, and return it.
     *
     * Keys come from admin_ai_keys via AiKeyResolver — same source as
     * the chatgpt-node Model dropdown, so the user can't pick a model
     * the server isn't configured for. No user-side billing toggles per
     * project policy (admin-only).
     */
    public function apiAiGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prompt'   => 'required|string|max:2000',
            'model'    => 'required|string|max:120',
            // Only providers AiAgentService::callProvider() actually
            // implements — keep this in sync if a new branch lands.
            'provider' => 'required|string|in:openai,anthropic,gemini',
        ]);

        $user = Auth::user();
        $workspace = $user?->current_workspace_id
            ? \App\Models\Workspace::find($user->current_workspace_id)
            : null;

        // Distinct error messages so the UI can show something useful
        // instead of the generic "Admin has not enabled this provider"
        // when the real issue is a workspace context loss (session expired,
        // dropped during cross-tab workspace switch, etc.).
        if (!$workspace) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_workspace',
                'message' => 'Could not resolve your active workspace. Try refreshing the page.',
            ], 422);
        }

        $resolved = \App\Services\AiKeyResolver::resolve($workspace, $data['provider']);
        if (!$resolved['key']) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_key',
                'message' => 'Admin has not enabled this provider in /admin/api-keys. Pick another provider or contact your admin.',
            ], 422);
        }

        $systemPrompt = <<<'SYS'
You design WhatsApp chat flows as JSON. Output STRICT JSON only — no
prose, no markdown, no code fences. The schema is:

{
  "flowNodes": [
    { "id": "n_<short>", "type": "<type>", "x": <int>, "y": <int>, "data": { ... } }
  ],
  "flowEdges": [
    { "id": "e_<short>", "source": "<nodeId>", "sourceHandle": "<handle>", "target": "<nodeId>" }
  ]
}

Available node types and their data shapes:
  trigger:         { kind: "keyword"|"qr"|"start", keywords: "hi, hello" }
  message:         { text: "..." }
  sequence:        { replies: [{ type: "text"|"image"|"video"|"audio"|"document", text|url, caption?, filename? }] }
  ask:             { prompt: "...", var: "name", validate?: "email"|"phone"|"number", options?: ["yes","no"] }
  buttons:         { prompt: "...", options: ["A","B","C"], var: "choice"  } (max 3 options; ports p0,p1,p2)
  list:            { prompt: "...", options: ["A","B",...], button: "View", var: "choice" } (up to 10)
  condition:       { conditions: [{ variable: "{{name}}", operator: "equals"|"contains"|"not_equals"|"is_empty", value: "x" }], operators: ["AND"|"OR"] } (ports: yes / no)
  delay:           { unit: "sec"|"min"|"hour"|"day", amount: 5 }
  template:        { tpl: "<template_name>", preview: "..." }
  ai:              { model: "gpt-4o-mini", prompt: "system prompt", save: "reply" }
  cta:             { actions: [{ type: "url"|"phone"|"copy", label: "Visit", value: "https://..." }] } (max 3)
  location:        { name: "...", address: "...", lat: 0, lng: 0 }
  poll:            { question: "...", options: ["A","B"] }
  tag:             { action: "add"|"remove", tag: "<name>", tagId?: "<id>" }
  assign:          { team: "<team>", userId?: "<user>", message: "internal note" }
  webhook:         { method: "GET"|"POST", url: "https://...", body: "...", save: "response", contentType: "application/json" }
  book_appointment:{ slotCount: 5, prompt: "Pick a time", confirmation: "Booked!" } (ports: booked / no_slots)
  whatsapp_shop:   { storeId: "<wa_catalog_id>", productItems: [{retailer_id:"sku", name:"...", price_minor:0, currency:""}], headerText:"", bodyText:"...", abandonedWaitMinutes: 5 } (ports: purchased / abandoned)
  woocommerce:     same shape as whatsapp_shop, storeId is the WC integration id
  shopify:         same shape as whatsapp_shop, storeId is the Shopify integration id
  end:             {}

Edge handles (sourceHandle):
  - Default ports: "out"
  - Multi-option (buttons/list/poll): "p0","p1","p2"...
  - Condition: "yes" or "no"
  - book_appointment: "booked" or "no_slots"
  - Commerce nodes: "purchased" or "abandoned"

Rules:
1. Always include exactly ONE "trigger" node as flowNodes[0].
2. End every branch with an "end" or with a terminal "message" + "end".
3. Layout left→right: increment x by 360 each step; y=200 for the main lane, ±260 for branches.
4. Make ids unique: "n_" + 6 lowercase alphanumerics for nodes, "e_" for edges.
5. Use {{var}} merge tags in messages to reference variables set by upstream "ask" nodes.
6. Do NOT use emojis anywhere.
7. Keep the flow concise — max 12 nodes.

Output ONLY the JSON object. No explanation. No code fences.
SYS;

        $ai = app(\App\Services\AiAgentService::class);
        $raw = $ai->callProvider(
            provider:     $data['provider'],
            model:        $data['model'],
            workspaceId:  (int) ($workspace?->id ?? 0),
            systemPrompt: $systemPrompt,
            userPrompt:   $data['prompt'],
            // A full flow (up to 12 nodes + edges) easily exceeds 2400 tokens,
            // and a JSON object cut off mid-write can't be parsed → the client's
            // "Model output was not valid flow JSON" error. Give it enough room
            // to finish the object.
            maxTokens:    8000,
            temperature:  0.4,
            jsonMode:     true, // force a strict JSON object (Gemini responseMimeType / OpenAI+Anthropic json_object)
        );

        if (!$raw) {
            return response()->json([
                'ok'      => false,
                'error'   => 'provider_failed',
                'message' => 'AI provider returned no content — check API key + model id.',
            ], 502);
        }

        // Models still occasionally wrap JSON in prose or ```json fences despite
        // the instruction + JSON mode. Be tolerant: strip fences wherever they
        // appear, then slice to the OUTERMOST {...} object before decoding — so
        // leading/trailing chatter ("Here is your flow:") no longer breaks it.
        $clean = trim($raw);
        $clean = preg_replace('/```(?:json)?/i', '', $clean);
        $clean = trim((string) $clean, " \t\n\r\0\x0B`");
        $first = strpos($clean, '{');
        $last  = strrpos($clean, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $clean = substr($clean, $first, $last - $first + 1);
        }

        $flow = json_decode($clean, true);
        if (!is_array($flow) || !isset($flow['flowNodes']) || !is_array($flow['flowNodes'])) {
            Log::warning('[AI-Flow] bad JSON from model: ' . substr($raw, 0, 400));
            return response()->json([
                'ok'      => false,
                'error'   => 'bad_json',
                'message' => 'Model output was not valid flow JSON. Try a clearer prompt.',
                'raw'     => mb_substr($raw, 0, 600),
            ], 422);
        }

        // Sanity: trim to safe limits + make sure ids are populated.
        $flow['flowNodes'] = array_slice(array_values($flow['flowNodes']), 0, 20);
        $flow['flowEdges'] = array_slice(array_values($flow['flowEdges'] ?? []), 0, 40);

        return response()->json([
            'ok'    => true,
            'flow'  => $flow,
            'model' => $data['model'],
        ]);
    }

    /**
     * POST /flows/{id}/enroll — manual enrollment endpoint for
     * trigger_kind='manual_enroll' flows. Accepts contact_ids[] or a
     * group identifier; iterates and calls FlowEnrollmentService for
     * each. Idempotent at the (flow_id, contact_id) UNIQUE constraint.
     */
    public function apiEnroll(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $flow = Flow::query()->forCurrentWorkspace()->find($id);
        if (!$flow) return response()->json(['ok' => false, 'error' => 'flow_not_found'], 404);
        if (!$flow->is_active) return response()->json(['ok' => false, 'error' => 'flow_inactive'], 422);

        $data = $request->validate([
            'contact_ids'   => 'nullable|array',
            'contact_ids.*' => 'integer',
            'group_name'    => 'nullable|string|max:191',
        ]);

        $wsId = $flow->workspace_id;
        $contacts = collect();
        if (!empty($data['contact_ids'])) {
            $contacts = \App\Models\Contact::query()
                ->whereIn('id', $data['contact_ids'])
                ->where('workspace_id', $wsId)
                ->get();
        } elseif (!empty($data['group_name'])) {
            $contacts = \App\Models\Contact::query()
                ->where('workspace_id', $wsId)
                ->get()
                ->filter(function ($c) use ($data) {
                    $groups = is_array($c->contact_group) ? $c->contact_group : [];
                    return in_array($data['group_name'], $groups, true);
                });
        }

        $enrollment = app(\App\Services\Flow\FlowEnrollmentService::class);
        $enrolled = 0; $failed = 0;
        foreach ($contacts as $c) {
            try { $enrollment->enroll($c, $flow); $enrolled++; }
            catch (\Throwable $e) { $failed++; }
        }

        return response()->json(['ok' => true, 'enrolled' => $enrolled, 'failed' => $failed]);
    }

    /**
     * GET /flows/{id}/subscribers — list flow subscribers + their state.
     * Used by the flow detail panel + flows index aggregates.
     */
    public function apiSubscribers(Request $request, int $id): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->find($id);
        if (!$flow) return response()->json(['ok' => false, 'error' => 'flow_not_found'], 404);

        $subs = \App\Models\FlowSubscriber::query()
            ->where('flow_id', $flow->id)
            ->with(['contact:id,first_name,last_name,country_code,mobile'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $counts = [
            'active'    => $subs->where('status', 'active')->count(),
            'paused'    => $subs->where('status', 'paused')->count(),
            'completed' => $subs->where('status', 'completed')->count(),
            'failed'    => $subs->where('status', 'failed')->count(),
        ];

        return response()->json([
            'ok'           => true,
            'counts'       => $counts,
            'trigger_kind' => $flow->trigger_kind,
            'subscribers'  => $subs->map(fn ($s) => [
                'id'             => $s->id,
                'contact_id'     => $s->contact_id,
                'contact_name'   => trim(($s->contact->first_name ?? '') . ' ' . ($s->contact->last_name ?? '')) ?: ('#' . $s->contact_id),
                'contact_phone'  => preg_replace('/\D+/', '', (string) ($s->contact->country_code . $s->contact->mobile)),
                'status'         => $s->status,
                'enrolled_at'    => $s->enrolled_at?->toIso8601String(),
                'failed_at'      => $s->failed_at?->toIso8601String(),
                'failure_reason' => $s->failure_reason,
            ]),
        ]);
    }

    /** POST /flows/{id}/subscribers/{cid}/pause */
    public function apiSubscriberPause(int $id, int $cid): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->find($id);
        if (!$flow) return response()->json(['ok' => false], 404);
        \App\Models\FlowSubscriber::query()
            ->where('flow_id', $flow->id)->where('contact_id', $cid)
            ->update(['status' => 'paused']);
        return response()->json(['ok' => true]);
    }

    /** POST /flows/{id}/subscribers/{cid}/resume */
    public function apiSubscriberResume(int $id, int $cid): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()->find($id);
        if (!$flow) return response()->json(['ok' => false], 404);
        $sub = \App\Models\FlowSubscriber::query()
            ->where('flow_id', $flow->id)->where('contact_id', $cid)
            ->first();
        if (!$sub) return response()->json(['ok' => false], 404);

        // Resume = un-mute the existing Node session. Just flip the DB
        // flag back to 'active' — Node never stops the session on pause
        // (the pause flag here is a Laravel-side gate to prevent FUTURE
        // re-enrollment from tag/group triggers while the operator
        // keeps the contact muted). Calling enroll() here would double-
        // fire the flow on contacts that still had a live session;
        // calling launchFlow() unconditionally would create a duplicate.
        $sub->update(['status' => 'active', 'failed_at' => null, 'failure_reason' => null]);
        return response()->json(['ok' => true]);
    }

    /** GET /flows/api/picker — tags + groups + devices for the trigger inspector. */
    public function apiPicker(Request $request): JsonResponse
    {
        $wsId = (int) ($request->user()->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 401);

        $tags = \App\Models\Tag::query()
            ->where('workspace_id', $wsId)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        // contact_groups is the canonical pivot table; group_join triggers
        // store the group id in trigger_value, so we surface real ids
        // (the contact_group JSON column on Contact is a denorm of these ids).
        $groups = \App\Models\ContactGroup::query()
            ->where('workspace_id', $wsId)
            ->orderBy('user_group')
            ->get(['id', 'user_group as name']);

        // Device list for the flow trigger / send-node pickers — EVERY enabled
        // engine's connected numbers (Unofficial + WABA + Twilio), so on a
        // multi-engine workspace the operator can pick which number a flow is
        // scoped to. senders() already merges all enabled engines and filters to
        // connected; we reshape its `engine:id` rows to the {id,device_name,
        // phone_number,provider} the builder expects.
        $devices = \App\Services\WorkspaceEngine::senders($wsId)->map(function ($s) {
            $key = (string) ($s['key'] ?? '');
            $id  = (int) (str_contains($key, ':') ? substr($key, strpos($key, ':') + 1) : 0);
            $eng = (string) ($s['engine'] ?? '');
            $label = (string) ($s['label'] ?? '');
            return (object) [
                'id'           => $id,
                'device_name'  => $label !== '' ? $label : (strtoupper($eng) . ' #' . $id),
                'phone_number' => (string) ($s['phone'] ?? ''),
                'provider'     => $eng,
            ];
        })->values();

        return response()->json([
            'ok'       => true,
            'tags'     => $tags,
            'groups'   => $groups,
            'devices'  => $devices,
        ]);
    }

    public function apiDefault(): JsonResponse
    {
        $flow = Flow::query()->forCurrentWorkspace()
            ->where('is_active', true)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->first();
        if (!$flow) return response()->json(['success' => false, 'message' => 'No default flow found'], 404);
        return response()->json([
            'success' => true,
            'data'    => ['flow' => $flow, 'flow_data' => $flow->decoded_flow_data],
        ]);
    }

    public function connectDevice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'flow_id'       => 'required|integer',
            'device_number' => 'required|string',
            'device_name'   => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $flow = Flow::query()->forCurrentWorkspace()->findOrFail($request->integer('flow_id'));
        FlowConnectedDevice::query()->where('flow_id', $flow->id)->update(['status' => 'disconnected']);
        $device = FlowConnectedDevice::create([
            'flow_id'        => $flow->id,
            'device_number'  => $request->string('device_number')->toString(),
            'device_name'    => $request->string('device_name')->toString() ?: null,
            'status'         => 'active',
            'connected_at'   => now(),
            'last_active_at' => now(),
        ]);
        return response()->json(['success' => true, 'data' => $device]);
    }

    public function disconnectDevice(int $id): JsonResponse
    {
        // Flow ownership is workspace-shared, so any teammate in the
        // owning workspace can disconnect its devices.
        $device = FlowConnectedDevice::query()
            ->whereHas('flow', fn ($q) => $q->forCurrentWorkspace())
            ->find($id);
        if (!$device) return response()->json(['success' => false, 'message' => 'Connection not found'], 404);
        $device->update(['status' => 'disconnected']);
        return response()->json(['success' => true]);
    }

    /**
     * Walk flow_data.flowNodes[*].flowReplies[*] and convert relative
     * media paths to fully-qualified URLs (matches old behavior).
     */
    private function normalizeMediaUrls(array $flowData): array
    {
        if (!isset($flowData['flowNodes']) || !is_array($flowData['flowNodes'])) return $flowData;
        foreach ($flowData['flowNodes'] as &$node) {
            if (empty($node['flowReplies']) || !is_array($node['flowReplies'])) continue;
            foreach ($node['flowReplies'] as &$reply) {
                $kind = $reply['flowReplyType'] ?? null;
                if (!in_array($kind, ['Image', 'Video', 'Audio', 'Document'], true)) continue;
                if (empty($reply['data']) || !is_string($reply['data'])) continue;
                if (!filter_var($reply['data'], FILTER_VALIDATE_URL)) {
                    $reply['data'] = url($reply['data']);
                }
            }
        }
        return $flowData;
    }

    /**
     * Pull the trigger config out of the React builder's trigger node and
     * map it to the flows.trigger_kind/value/device_id columns. Lives here
     * (not in a model accessor) so the SAVE path is the single source of
     * truth — apiSave runs this on every update so renaming a tag or
     * editing the trigger kind correctly re-flows to the columns.
     */
    private function extractTriggerColumns(array $flowData): array
    {
        $trigger = null;
        foreach (($flowData['flowNodes'] ?? []) as $n) {
            if (($n['type'] ?? null) === 'trigger') { $trigger = $n; break; }
        }
        $d = is_array($trigger['data'] ?? null) ? $trigger['data'] : [];
        $kind = (string) ($d['kind'] ?? 'keyword');
        if (!in_array($kind, \App\Models\Flow::TRIGGER_KINDS, true)) {
            $kind = 'keyword';
        }
        $value = null;
        if ($kind === 'tag_added')   $value = (int) ($d['tagId']   ?? 0) ?: null;
        if ($kind === 'group_join')  $value = (int) ($d['groupId'] ?? 0) ?: null;
        // Sales Pipeline bridge — fire when a deal enters this stage.
        if ($kind === 'deal_stage_changed') $value = (int) ($d['stageId'] ?? 0) ?: null;
        // Value-less event triggers match on trigger_value = 0 (see
        // FlowEnrollmentService::flowsForWorkspace), so store 0 not null.
        if (in_array($kind, ['contact_created', 'opt_in', 'order_placed'], true)) $value = 0;
        $deviceId = (int) ($d['deviceId'] ?? 0) ?: null;
        // Keyword string (comma-separated) lives only in the trigger node's
        // data; mirror it to a column so the model's saved-hook can sync a
        // keyword_replies row that actually fires the flow on inbound.
        $keywords = $kind === 'keyword' ? (trim((string) ($d['keywords'] ?? '')) ?: null) : null;

        return [
            'trigger_kind'      => $kind,
            'trigger_value'     => $value,
            'trigger_device_id' => $deviceId,
            'trigger_keywords'  => $keywords,
        ];
    }

    /**
     * "Most-used" flow for the recommended/featured slot. We don't track
     * per-flow usage stats yet, so the proxy is: prefer published+active
     * flows, then most recently updated. Falls back to any flow if the
     * user hasn't published anything.
     */
    private function mostUsedFlow(?int $userId): ?Flow
    {
        return Flow::query()
            ->forCurrentWorkspace()
            ->orderByDesc('is_published')
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function statusCounts(?int $userId): array
    {
        $rows = Flow::query()->forCurrentWorkspace()->get();
        return [
            'all'    => $rows->count(),
            'live'   => $rows->where('is_published', true)->where('is_active', true)->count(),
            'paused' => $rows->where('is_published', true)->where('is_active', false)->count(),
            'draft'  => $rows->where('is_published', false)->count(),
        ];
    }

    private function categoryCounts(?int $userId): array
    {
        $rows = Flow::query()->forCurrentWorkspace()
            ->selectRaw('COALESCE(category, "uncategorized") as cat, COUNT(*) as c')
            ->groupBy('category')
            ->pluck('c', 'cat')
            ->all();
        $rows['all'] = array_sum($rows);
        return $rows;
    }
}
