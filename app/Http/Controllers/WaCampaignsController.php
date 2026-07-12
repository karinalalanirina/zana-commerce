<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Message;
use App\Models\SystemSetting;
use App\Models\WpCampaign;
use App\Models\WpCampaignContact;
use App\Services\WalletService;
use App\Services\WhatsAppDispatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * WhatsApp Campaigns CRUD — ported from
 * D:\wadesk_2806\New folder\app\Http\Controllers\WhatsAppCampaignController.php
 *
 * Adapted for the new project:
 *   - dropped the multi-tenancy `$site_name` route segment
 *   - dropped Spatie permission checks and PackageLimites enforcement
 *   - dropped the external Node.js / Facebook Http::post calls (TODO: dispatch
 *     an internal SendWaCampaign job once the queue infra lands)
 *
 * Operator-facing methods only. Webhook-style endpoints (trackResponse,
 * trackClick, unsubscribe) and internal Node sync helpers are intentionally
 * not ported.
 */
class WaCampaignsController extends Controller
{
    public function __construct(
        private readonly WhatsAppDispatcher $dispatcher,
        private readonly WalletService $wallet,
    ) {}

    /**
     * Whether the active engine REQUIRES Meta-approved templates.
     * Baileys + Twilio are message-stream APIs that take any text body
     * (templates are just convenience snippets), so any template works.
     * WABA (Meta Cloud) only accepts the exact templates Meta has
     * approved on a phone-number basis.
     */
    private function requiresApprovedTemplates(): bool
    {
        $allowed = SystemSetting::get('allowed_send_methods', ['baileys']);
        $allowed = is_array($allowed) ? $allowed : [$allowed];
        $default = SystemSetting::get('default_send_method', 'baileys');
        $active  = in_array($default, $allowed, true) ? $default : ($allowed[0] ?? 'baileys');
        return $active === 'waba';
    }

    // -----------------------------------------------------------------
    // Listing + create form
    // -----------------------------------------------------------------

    public function index(Request $request)
    {
        // Filter params — sidebar status, sidebar type, top-bar
        // range, and the live-search query string. All five flow
        // through `?status=&type=&range=&q=` so the URL is the
        // single source of truth and the JS can re-fetch from
        // `?partial=1` whenever the user clicks a filter without
        // re-rendering the whole page.
        $statusFilter = $request->string('status')->toString() ?: 'all';
        $typeFilter   = $request->string('type')->toString()   ?: 'all';
        $rangeFilter  = $request->string('range')->toString()  ?: 'all';
        $search       = $request->string('q')->toString();

        $allCampaigns = WpCampaign::query()->forCurrentWorkspace()->forCurrentEngine()->orderBy('id', 'desc')->get();

        $campaigns = $allCampaigns;
        if ($statusFilter === 'recently_created') {
            $campaigns = $campaigns->where('created_at', '>=', now()->subDays(7));
        } elseif ($statusFilter === 'recently_updated') {
            $campaigns = $campaigns->where('updated_at', '>=', now()->subDays(7));
        } elseif ($statusFilter !== 'all') {
            $campaigns = $campaigns->where('status', $statusFilter);
        }
        if ($typeFilter === 'text') {
            $campaigns = $campaigns->whereIn('campaign_type', ['text', 'custom', 'media', 'button']);
        } elseif ($typeFilter !== 'all') {
            $campaigns = $campaigns->where('campaign_type', $typeFilter);
        }
        if ($rangeFilter !== 'all') {
            $cutoff = match ($rangeFilter) {
                '7d'  => now()->subDays(7),
                '30d' => now()->subDays(30),
                '90d' => now()->subDays(90),
                default => null,
            };
            if ($cutoff) $campaigns = $campaigns->where('created_at', '>=', $cutoff);
        }
        if ($search !== '') {
            // `campaign_name` is encrypted-at-rest — LIKE on
            // ciphertext matches nothing, so we filter the
            // hydrated collection by the decrypted plaintext.
            $needle = mb_strtolower($search);
            $campaigns = $campaigns->filter(fn ($c) => str_contains(mb_strtolower((string) $c->campaign_name), $needle));
        }
        $campaigns = $this->paginateCollection($campaigns->values(), $request, 12);

        // Roll the per-recipient wp_campaign_contacts log up into the
        // delivered/read/responded/clicked counts for the KPI strip + the
        // per-campaign cards. Meta status webhooks patch the log rows, and the
        // aggregate columns can lag them, so compute the true counts here in
        // ONE grouped query and override the in-memory models (display only —
        // the webhook and the detail page persist the columns). Without this
        // the KPI cards and campaign cards showed 0 while the funnel was right.
        $campaignIds = $allCampaigns->pluck('id')->all();
        if (!empty($campaignIds)) {
            $aggById = \DB::table('wp_campaign_contacts')
                ->whereIn('campaign_id', $campaignIds)
                ->selectRaw('campaign_id, ' . WpCampaign::aggregateSelectSql())
                ->groupBy('campaign_id')
                ->get()
                ->keyBy('campaign_id');
            $applyAgg = function ($c) use ($aggById) {
                $a = $aggById->get($c->id);
                if (!$a) return;
                $c->delivered_count = (int) $a->delivered;
                $c->read_count      = (int) $a->read_c;
                $c->responded_count = (int) $a->responded;
                $c->clicked_count   = (int) $a->clicked;
            };
            $allCampaigns->each($applyAgg);
            collect($campaigns->items())->each($applyAgg);
        }

        // Sidebar "Message type" counts — always derived from the
        // unfiltered set so the counts represent "what's available
        // to filter to", not "what survived the current filter".
        $messageTypes = [
            'text'     => $allCampaigns->whereIn('campaign_type', ['text', 'custom', 'media', 'button'])->count(),
            'template' => $allCampaigns->where('campaign_type', 'template')->count(),
            'flow'     => $allCampaigns->where('campaign_type', 'flow')->count(),
        ];

        // Sidebar status counts — same reasoning. Derived from
        // the full collection so users see real totals regardless
        // of which filter is currently active.
        $statusCounts = [
            'all'                => $allCampaigns->count(),
            'recently_created'   => $allCampaigns->where('created_at', '>=', now()->subDays(7))->count(),
            'recently_updated'   => $allCampaigns->where('updated_at', '>=', now()->subDays(7))->count(),
            'scheduled'          => $allCampaigns->where('status', 'scheduled')->count(),
            'running'            => $allCampaigns->where('status', 'running')->count(),
            'completed'          => $allCampaigns->where('status', 'completed')->count(),
            'failed'             => $allCampaigns->where('status', 'failed')->count(),
        ];

        // Delivery health — compute the average delivery rate across campaigns
        // that actually attempted to send, and surface a "warning" tone when
        // any campaign has > 5 failures so the sidebar tip card can switch
        // copy without a hardcoded string. (Workspace-wide, not filtered.)
        $sentTotal      = (int) $allCampaigns->sum('sent_count');
        $deliveredTotal = (int) $allCampaigns->sum('delivered_count');
        $avgDeliveryRate = $sentTotal > 0 ? ($deliveredTotal / $sentTotal) * 100 : 0;
        $failingCampaigns = $allCampaigns->where('failed_count', '>', 5)->count();
        $deliveryHealth = [
            'avg_delivery_rate'  => round($avgDeliveryRate, 1),
            'failing_campaigns'  => $failingCampaigns,
            'status'             => $failingCampaigns > 0 ? 'warning' : 'healthy',
        ];

        // Queue health — Template approvals: no Templates model yet, so the
        // approval ratio defaults to 100% (TODO: wire to Template::where('status','approved')
        // once the model lands). Device readiness pulls live counts from the
        // Device table when present. Retry backlog == sum of failed_count.
        // Devices-ready tile — engine-aware so a WABA workspace doesn't
        // see Baileys phone counts here. Baileys counts the devices
        // table; WABA / Twilio count wa_provider_configs rows of the
        // active engine.
        // Multi-engine: a workspace can run Baileys + WABA + Twilio at once,
        // so the devices-ready tile must SUM connected/total senders across
        // EVERY enabled engine — not just the single default. For a single-
        // engine workspace enginesFor() == [default], so this sum equals the
        // old single-engine branch (byte-identical). Baileys counts the
        // devices table; WABA / Twilio count wa_provider_configs rows of that
        // provider.
        $wsIdForRow = $request->user()?->current_workspace_id;
        $totalDevices     = 0;
        $connectedDevices = 0;
        foreach (\App\Services\WorkspaceEngine::enginesFor($wsIdForRow) as $engineForRow) {
            if ($engineForRow === \App\Services\WorkspaceEngine::ENGINE_BAILEYS && class_exists(\App\Models\Device::class)) {
                $totalDevices     += \App\Models\Device::query()->forCurrentWorkspace()->count();
                $connectedDevices += \App\Models\Device::query()->forCurrentWorkspace()->where('status', 'connected')->count();
            } else {
                $waba = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $wsIdForRow)
                    ->where('provider', $engineForRow)
                    ->get(['status']);
                $totalDevices     += $waba->count();
                $connectedDevices += $waba->where('status', 'connected')->count();
            }
        }
        $deviceReady = $totalDevices > 0 ? "{$connectedDevices}/{$totalDevices}" : 'N/A';
        $queueHealth = [
            // TODO: replace with real Template::where('status','approved')->count() / Template::count() * 100 once a Templates model lands.
            'template_approval_rate' => 100,
            'devices_ready'          => $deviceReady,
            'retry_backlog'          => (int) $allCampaigns->sum('failed_count'),
        ];

        $stats = [
            'total'   => $allCampaigns->count(),
            'queued'  => $statusCounts['scheduled'],
            'running' => $statusCounts['running'],
            'sent'    => $statusCounts['completed'],
            'failed'  => $statusCounts['failed'],
            // KPI tiles roll-ups — full workspace, not filtered.
            'sent_total'      => $sentTotal,
            'delivered_total' => $deliveredTotal,
            'read_total'      => (int) $allCampaigns->sum('read_count'),
            'failed_total'    => (int) $allCampaigns->sum('failed_count'),
            'processing'      => $statusCounts['running'],
            // Sidebar/aside derived metrics.
            'messageTypes'    => $messageTypes,
            'statusCounts'    => $statusCounts,
            'deliveryHealth'  => $deliveryHealth,
            'queueHealth'     => $queueHealth,
        ];

        $payload = [
            'campaigns'       => $campaigns,
            'stats'           => $stats,
            'currentStatus'   => $statusFilter,
            'currentType'     => $typeFilter,
            'currentRange'    => $rangeFilter,
            'currentSearch'   => $search,
        ];

        if ($request->wantsJson() || $request->boolean('partial')) {
            return response()->json([
                'ok'           => true,
                'cards'        => view('user.wa-campaigns._cards', ['campaigns' => $campaigns])->render(),
                'stats'        => $stats,
                'statusCounts' => $statusCounts,
                'messageTypes' => $messageTypes,
                'pagination'   => view('user.partials.pagination', ['paginator' => $campaigns, 'dataAttr' => 'data-wac-page', 'label' => 'campaigns'])->render(),
                'shown'        => $campaigns->count(),
                'total'        => $campaigns->total(),
                'page'         => $campaigns->currentPage(),
            ]);
        }

        return view('user.wa-campaigns.index', $payload);
    }

    /**
     * #4 — Downloadable sample CSV for the campaign bulk-recipient upload.
     * Columns match what the CSV importer reads: name + country_code + phone.
     */
    public function sampleCsv(): \Symfony\Component\HttpFoundation\Response
    {
        $csv = implode("\n", [
            'name,country_code,phone',
            'Aarav Sharma,91,9812345678',
            'Priya Patel,91,9898765432',
        ]) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="campaign-recipients-sample.csv"',
        ]);
    }

    public function create(Request $request): View
    {
        // Workspace-shared pickers — every asset created in the current
        // workspace shows up, regardless of which teammate added it.
        // Device picker is engine-aware: Baileys workspaces see paired
        // phones from `devices`; WABA / Twilio surface wa_provider_configs
        // rows as pseudo-devices so the operator can never pick a wrong-
        // engine sender that would silently fail at dispatch.
        $wsId   = $request->user()?->current_workspace_id;
        $engine = \App\Services\WorkspaceEngine::for($wsId);
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS && class_exists(\App\Models\Device::class)) {
            // Connected senders only — a disconnected phone can't run a
            // campaign, so keep it out of the picker. The /devices page
            // and the index KPIs still count every device.
            $devices = \App\Models\Device::query()->forCurrentWorkspace()
                ->where('status', 'connected')->orderByDesc('id')->get();
        } else {
            $devices = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('connected_at')
                ->get()
                ->map(function ($cfg) {
                    return (object) [
                        'id'           => $cfg->id,
                        'device_name'  => $cfg->display_label ?: ('WABA #' . $cfg->id),
                        'country_code' => '',
                        'phone_number' => $cfg->phone_number,
                        'status'       => $cfg->status,
                        'active'       => $cfg->status === \App\Models\WaProviderConfig::STATUS_CONNECTED,
                    ];
                });
        }
        // Multi-engine: every connected sender across ALL enabled engines,
        // for the unified <x-sender-picker> (composite engine:id keys). The
        // single-engine $devices list above is kept for back-compat / empty-
        // state copy.
        $senders = \App\Services\WorkspaceEngine::senders($wsId);

        $contacts  = Contact::query()->forCurrentWorkspace()->orderByDesc('id')->get();
        $groups    = ContactGroup::query()->forCurrentWorkspace()->orderByDesc('id')->get();
        // Load all of the user's templates — show their status next
        // to the name so the operator picks an approved one. The store
        // step rejects non-approved templates.
        $templates = class_exists(\App\Models\WaTemplate::class)
            ? \App\Models\WaTemplate::query()->forCurrentWorkspace()->providerLive()->with('provider')->orderByDesc('id')->get()
            : collect();
        $flows = class_exists(\App\Models\Flow::class)
            ? \App\Models\Flow::query()->forCurrentWorkspace()->orderByDesc('id')->get()
            : collect();

        // Pre-compute per-group member counts. The contacts.contact_group
        // column is encrypted JSON, so we hydrate once and tally in PHP
        // (cheaper than a per-group Eloquent query). Workspace-scoped
        // so foreign contacts can't inflate another tenant's counts.
        $allContacts = Contact::query()->forCurrentWorkspace()->get(['id', 'contact_group']);
        $groupCounts = [];
        foreach ($groups as $g) {
            $gid = (string) $g->id;
            $groupCounts[$g->id] = $allContacts->filter(function ($c) use ($gid) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                return in_array($gid, array_map('strval', $list), true);
            })->count();
        }

        $requiresApprovedTemplates = $this->requiresApprovedTemplates();

        // Null on the create path — the edit view reuses the same picker
        // payload but pre-fills from an existing campaign when present.
        $campaign = null;

        return view('user.wa-campaigns.create', compact(
            'devices', 'senders', 'contacts', 'groups', 'groupCounts',
            'templates', 'flows', 'requiresApprovedTemplates', 'campaign',
        ));
    }

    /**
     * GET /wa-campaigns/{id}/edit — pre-filled editor for a campaign that is
     * still mutable. Mirrors the picker payload built in create() so the
     * device / contact / group / template selects render identically, then
     * hands the loaded campaign to the focused edit view. Only draft,
     * scheduled or paused campaigns can be edited; anything in-flight or
     * finished is redirected back to its detail page. The server-side guard
     * in update() backs this up so a forged POST can't bypass it.
     */
    public function edit(int $id, Request $request): \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        if (!in_array($campaign->status, ['draft', 'paused', 'scheduled'], true)) {
            return redirect()
                ->route('user.wa-campaigns.detail', $campaign->id)
                ->with('status', 'Only draft, scheduled or paused campaigns can be edited.');
        }

        $wsId   = $request->user()?->current_workspace_id;
        $engine = \App\Services\WorkspaceEngine::for($wsId);
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS && class_exists(\App\Models\Device::class)) {
            $devices = \App\Models\Device::query()->forCurrentWorkspace()
                ->where('status', 'connected')->orderByDesc('id')->get();
        } else {
            $devices = \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->where('provider', $engine)
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderByDesc('connected_at')
                ->get()
                ->map(function ($cfg) {
                    return (object) [
                        'id'           => $cfg->id,
                        'device_name'  => $cfg->display_label ?: ('WABA #' . $cfg->id),
                        'country_code' => '',
                        'phone_number' => $cfg->phone_number,
                        'status'       => $cfg->status,
                        'active'       => $cfg->status === \App\Models\WaProviderConfig::STATUS_CONNECTED,
                    ];
                });
        }
        $templates = class_exists(\App\Models\WaTemplate::class)
            ? \App\Models\WaTemplate::query()->forCurrentWorkspace()->providerLive()->with('provider')->orderByDesc('id')->get()
            : collect();
        $requiresApprovedTemplates = $this->requiresApprovedTemplates();

        // Recipient ids already attached to this campaign — used to mark
        // the matching checkboxes on the edit form. Stored on the per-contact
        // log table, so we pull the distinct contact ids back.
        $recipientIds = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('contact_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $contacts = Contact::query()->forCurrentWorkspace()->orderByDesc('id')->get();

        // Multi-engine sender set (all enabled engines) for <x-sender-picker>.
        $senders = \App\Services\WorkspaceEngine::senders($wsId);

        return view('user.wa-campaigns.edit', compact(
            'campaign', 'devices', 'senders', 'contacts', 'templates',
            'requiresApprovedTemplates', 'recipientIds',
        ));
    }

    // -----------------------------------------------------------------
    // Store
    // -----------------------------------------------------------------

    /**
     * Reject a campaign submit so the user actually SEES why. The create form
     * posts via fetch (Accept: application/json), and a back()->withErrors()
     * 302 is silently followed by fetch → res.ok=true, no error shown → the
     * "Launch" button just hangs. Return a JSON 422 the JS surfaces as a toast,
     * fall back to a redirect for non-AJAX callers.
     */
    private function rejectForm(Request $request, string $field, string $message)
    {
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['ok' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422);
        }
        return back()->withErrors([$field => $message])->withInput();
    }

    public function store(Request $request)
    {
        // Plan: feature flag + numeric cap.
        \App\Services\PlanLimitGuard::feature($request->user()->currentWorkspace, 'campaign');
        // wpcampaigns table has no user_id column — `where('user_id', ...)`
        // would throw at SQL level. Count by workspace_id, which matches
        // the plan-limit's semantics (the workspace owns the campaigns).
        \App\Services\PlanLimitGuard::check(
            $request->user()->currentWorkspace,
            'total_campaigns_limit',
            \App\Models\WpCampaign::where('workspace_id', $request->user()->current_workspace_id)->count(),
        );

        $validated = $request->validate([
            'campaign_name'           => 'required|string|max:191',
            'device_id'               => 'nullable|integer',
            // Multi-engine: unified picker posts a composite `engine:id` key.
            // device_id stays accepted for back-compat (legacy single-engine form).
            'sender'                  => 'nullable|string|max:64',
            'campaign_type'           => 'required|in:text,template,button,flow,media,custom',
            'status'                  => 'nullable|string|max:32',
            'ab_testing'              => 'nullable|boolean',
            'ab_split'                => 'nullable|integer|min:0|max:100',
            'custom_message_b'        => 'nullable|string',
            'custom_message'          => 'required_if:campaign_type,text,custom,button,media|nullable|string',
            'custom_header'           => 'nullable|string|max:255',
            'custom_footer'           => 'nullable|string|max:255',
            'custom_buttons'          => 'nullable|array',
            'custom_quick_replies'    => 'nullable|array',
            // Positional-placeholder map for the CUSTOM message body. The
            // composer's `/`-attribute picker inserts {{1}} {{2}} tokens and
            // records {"1":"order_id"} here (compose-textarea emits it as
            // `custom_message_variable_map`). resolveCampaignBody feeds it to
            // AttributeResolver so the slots resolve to real workspace
            // attribute values at send time instead of shipping literal {{1}}.
            'custom_message_variable_map' => 'nullable|string',
            'template_id'             => 'nullable|integer',
            'template_id_a'           => 'nullable|integer',
            'template_id_b'           => 'nullable|integer',
            'flow_id'                 => 'nullable|integer',
            'flow_id_b'               => 'nullable|integer',
            'use_attributes'          => 'nullable|boolean',
            'tracking_enabled'        => 'nullable|boolean',
            'schedule_type'           => 'required|in:now,scheduled,recurring',
            'send_date'               => 'nullable|date',
            'send_time'               => 'nullable',
            'timezone'                => ['nullable', 'string', \Illuminate\Validation\Rule::in(\DateTimeZone::listIdentifiers())],
            'repeat_interval'         => 'nullable|in:daily,weekly,monthly',
            'repeat_until'            => 'nullable|date',
            // Smart Delivery (anti-ban) — all optional; blank = global default.
            'throttle_min_sec'        => 'nullable|integer|min:0|max:3600',
            'throttle_max_sec'        => 'nullable|integer|min:0|max:3600|gte:throttle_min_sec',
            'batch_size'              => 'nullable|integer|min:1|max:10000',
            'batch_pause_min'         => 'nullable|integer|min:0|max:1440',
            'daily_limit'             => 'nullable|integer|min:1|max:100000',
            'window_start'            => 'nullable|date_format:H:i',
            'window_end'              => 'nullable|date_format:H:i',
            'recipients'              => 'nullable|array',
            'recipients.*'            => 'integer',
            'groups'                  => 'nullable|array',
            'groups.*'                => 'integer',
            'manual_numbers'          => 'nullable|string',
            'csv_file'                => 'nullable|file|mimes:csv,txt|max:5120',
            // Rich CUSTOM-campaign media — an image / video / document that
            // rides WITH the caption (custom_message) + buttons as a product
            // card. Only ONE per campaign (first non-empty wins below).
            'custom_image'            => 'nullable|file|mimes:jpg,jpeg,png|max:2048',     // ≤2MB
            'custom_video'            => 'nullable|file|mimes:mp4|max:16384',             // ≤16MB
            'custom_document'         => 'nullable|file|mimes:pdf,doc,docx|max:16384',    // ≤16MB
        ]);

        // Named → positional normalization for the CUSTOM body. The
        // composer now inserts named tokens ({{order_id}}) for readability;
        // mirror the template path and store POSITIONAL {{1}} + a slot→key
        // map so storage stays canonical. Idempotent: a body that is
        // already positional ({{1}}) is left untouched and keeps whatever
        // custom_message_variable_map the form carried. AttributeResolver
        // resolves both shapes at send time, so this is purely about keeping
        // what we persist consistent with templates.
        [$normMsg, $normMap] = $this->normalizeCustomMessage(
            (string) $request->input('custom_message', ''),
            (string) $request->input('custom_message_variable_map', '')
        );
        $request->merge([
            'custom_message'              => $normMsg,
            'custom_message_variable_map' => $normMap,
        ]);

        // WhatsApp guardrails — screen the campaign's free-text body ONCE
        // (it's the same body for every recipient). No-op unless the admin
        // set /admin/security guardrails to monitor/enforce; monitor only
        // logs. Fail-open inside SendGate. Template campaigns carry no free
        // body here, so this only bites custom/text campaigns.
        try {
            \App\Support\SendGate::screenBody((string) $request->input('custom_message'), [
                'source'       => 'campaign',
                'workspace_id' => (int) (optional($request->user())->current_workspace_id ?? 0),
            ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        \Illuminate\Support\Facades\Log::info('[CAMPAIGN] store request', [
            'user_id'        => Auth::id(),
            'campaign_name'  => $request->input('campaign_name'),
            'campaign_type'  => $request->input('campaign_type'),
            'device_id'      => $request->input('device_id'),
            'recipients_n'   => count($request->input('recipients', [])),
            'groups_n'       => count($request->input('groups', [])),
            'manual_present' => !empty($request->input('manual_numbers')),
            'csv_present'    => $request->hasFile('csv_file'),
            'schedule_type'  => $request->input('schedule_type'),
        ]);

        // Compute total recipients from the union of explicit contact ids and
        // contacts in the selected groups.
        //
        // SECURITY: the explicit recipients[] ids are validated only as
        // integers (no exists/ownership rule), so a forged request could name
        // another tenant's sequential contact ids. Filter them through the
        // caller's workspace exactly like the group-expansion path below does —
        // foreign ids are dropped, same-workspace ids pass through unchanged.
        $rawRecipientIds = collect($request->input('recipients', []))->map(fn ($v) => (int) $v);
        $contactIds = $rawRecipientIds->isEmpty()
            ? collect()
            : Contact::query()->forCurrentWorkspace()
                ->whereIn('id', $rawRecipientIds->all())
                ->pluck('id')->map(fn ($v) => (int) $v);
        $groupIds   = collect($request->input('groups', []))->map(fn ($v) => (string) $v);

        if ($groupIds->isNotEmpty()) {
            // Workspace-scoped — never hydrate another tenant's
            // contacts when expanding the chosen groups.
            $groupMembers = Contact::query()
                ->forCurrentWorkspace()
                ->get(['id', 'contact_group'])
                ->filter(function ($c) use ($groupIds) {
                    $list = is_array($c->contact_group) ? $c->contact_group : [];
                    foreach ($list as $gid) {
                        if ($groupIds->contains((string) $gid)) return true;
                    }
                    return false;
                })
                ->pluck('id');
            $contactIds = $contactIds->merge($groupMembers)->unique()->values();
        }

        // Manual numbers (textarea, one per line / comma-separated) and
        // CSV upload — both pass arbitrary phone numbers that aren't
        // tied to an existing Contact row. We materialise an on-the-fly
        // Contact for each one (so the dispatch pipeline + recipient log
        // stays homogeneous) and merge the new ids into $contactIds.
        $extraNumbers = $this->parseManualNumbers((string) $request->input('manual_numbers', ''));
        if ($request->hasFile('csv_file')) {
            $extraNumbers = $extraNumbers->merge($this->parseCsvNumbers($request->file('csv_file')));
        }
        $extraNumbers = $extraNumbers->map(fn ($n) => preg_replace('/\D+/', '', (string) $n))
            ->filter(fn ($n) => strlen((string) $n) >= 8)
            ->unique()
            ->values();

        if ($extraNumbers->isNotEmpty()) {
            \Illuminate\Support\Facades\Log::info('[CAMPAIGN] adding manual/csv numbers', [
                'count' => $extraNumbers->count(),
                'first' => $extraNumbers->take(3)->all(),
            ]);
            $wsId = (int) (Auth::user()->current_workspace_id ?? 0);
            $uid  = Auth::id();
            foreach ($extraNumbers as $phone) {
                // Auto-save (or reuse) a Contact for every manual/CSV number so
                // it lands in the Contacts table — O(1) dedup by phone hash.
                $c = Contact::rememberPhone($wsId, $uid, (string) $phone, 'Recipient · ' . substr((string) $phone, -4));
                if ($c) {
                    $contactIds->push($c->id);
                }
            }
            $contactIds = $contactIds->unique()->values();
        }

        $totalRecipients = $contactIds->count();
        \Illuminate\Support\Facades\Log::info('[CAMPAIGN] recipients resolved', ['total' => $totalRecipients]);

        $scheduleType = $request->input('schedule_type');
        $resolvedStatus = $scheduleType === 'now' ? 'running' : 'scheduled';

        // Provider-aware template gate. WABA needs Meta-approved
        // templates; Baileys/Twilio accept any.
        if ($request->input('campaign_type') === 'template' && $request->input('template_id')) {
            $tpl = \App\Models\WaTemplate::query()
                ->forCurrentWorkspace()
                ->find($request->input('template_id'));
            if (!$tpl) {
                Log::warning('[CAMPAIGN] REJECTED at template gate: template not found', ['template_id' => $request->input('template_id')]);
                return $this->rejectForm($request, 'template_id', 'Template not found.');
            }
            if ($this->requiresApprovedTemplates() && !in_array($tpl->status, ['approved', 'public'], true)) {
                Log::warning('[CAMPAIGN] REJECTED at template gate: WABA needs an approved template', [
                    'template_id' => $tpl->id, 'name' => $tpl->template_name, 'status' => $tpl->status,
                ]);
                return $this->rejectForm($request, 'template_id', 'WABA engine requires a Meta-approved template. This template is "' . $tpl->status . '".');
            }

            // Auth/OTP templates can't be broadcast/campaigned. Each
            // recipient needs a unique verifiable code that only the
            // merchant's own backend can mint. Same safety rail as
            // /broadcasts. Force 1:1 sends via the transactional API.
            if ($tpl->template_type === 'auth') {
                Log::warning('[CAMPAIGN] REJECTED at template gate: auth/OTP template cannot be campaigned', ['template_id' => $tpl->id]);
                return $this->rejectForm($request, 'template_id', 'Authentication (OTP) templates cannot be sent via campaign — each recipient needs a unique verifiable code. Send them 1:1 from your backend using the transactional template send endpoint instead.');
            }

            // WABA-v2 templates also get the full quality/paused/media gate.
            if ($tpl->meta_template_id
                && \App\Models\SystemSetting::get('waba_templates_v2_enabled', false)) {
                $reasons = [];
                if (strtoupper((string) $tpl->meta_status) !== 'APPROVED') {
                    $reasons[] = "Template is not approved by Meta yet (status: {$tpl->meta_status}).";
                }
                if ($tpl->paused_until && $tpl->paused_until->isFuture()) {
                    $reasons[] = 'Template is paused until ' . $tpl->paused_until->format('Y-m-d H:i') . '.';
                }
                $floor = strtoupper((string) \App\Models\SystemSetting::get('waba_template_quality_floor', 'YELLOW'));
                $rank  = ['UNKNOWN' => 1, 'RED' => 0, 'YELLOW' => 2, 'GREEN' => 3];
                $score = strtoupper((string) ($tpl->quality_score ?: 'UNKNOWN'));
                // UNKNOWN = Meta hasn't rated the template yet — true of EVERY
                // brand-new approved template (it only earns a rating AFTER it
                // starts sending). Blocking it was a chicken-and-egg that stopped
                // any new template from ever being campaigned. Only enforce the
                // quality floor for ACTUAL ratings (RED / YELLOW / GREEN).
                if ($score !== 'UNKNOWN' && ($rank[$score] ?? 1) < ($rank[$floor] ?? 2)) {
                    $reasons[] = "Template quality is {$score} (floor: {$floor}).";
                }
                if (!empty($tpl->attachment_type) && !in_array(strtoupper($tpl->attachment_type), ['NONE','TEXT','LOCATION'], true)
                    && !empty($tpl->attachment_file)) {
                    $url = media_url($tpl->attachment_file);
                    $urlErr = $this->mediaUrlReachableForMeta($url);
                    if ($urlErr) $reasons[] = $urlErr;
                }
                if (!empty($reasons)) {
                    Log::warning('[CAMPAIGN] REJECTED at template gate: WABA-v2 quality/paused/media', [
                        'template_id' => $tpl->id, 'name' => $tpl->template_name, 'reasons' => $reasons,
                    ]);
                    return $this->rejectForm($request, 'template_id', 'Cannot use this template: ' . implode(' ', $reasons));
                }
            }
        }

        // A/B variant B — OWNERSHIP only. The variant-B template/flow ids were
        // persisted + sent WITHOUT a workspace scope, so a forged id could point
        // at another tenant's template/flow. Reject a foreign id here. This is
        // ownership-only (not the full approval gate) so the normal web A/B flow
        // — which only ever offers this workspace's own items — is unaffected;
        // it fires solely on a cross-tenant id.
        if ($request->filled('template_id_b')
            && !\App\Models\WaTemplate::forCurrentWorkspace()->whereKey((int) $request->input('template_id_b'))->exists()) {
            return $this->rejectForm($request, 'template_id_b', 'Variant B template not found in this workspace.');
        }
        if ($request->filled('flow_id_b')
            && !\App\Models\Flow::forCurrentWorkspace()->whereKey((int) $request->input('flow_id_b'))->exists()) {
            return $this->rejectForm($request, 'flow_id_b', 'Variant B flow not found in this workspace.');
        }

        // Rich CUSTOM-campaign media. Store the uploaded file on the `public`
        // disk and remember the path on the matching column. Only ONE media
        // per campaign — first non-empty of image / video / document wins.
        // The column that holds the path also encodes the media TYPE (image
        // → custom_image, etc.), which dispatchCampaignNow reads back below.
        $customImage = $customVideo = $customDocument = null;
        if ($request->hasFile('custom_image')) {
            $customImage = $request->file('custom_image')->store('campaign-media', media_disk());
            Log::warning('[CAMPAIGN] custom media stored', ['type' => 'image', 'path' => $customImage]);
        } elseif ($request->hasFile('custom_video')) {
            $customVideo = $request->file('custom_video')->store('campaign-media', media_disk());
            Log::warning('[CAMPAIGN] custom media stored', ['type' => 'video', 'path' => $customVideo]);
        } elseif ($request->hasFile('custom_document')) {
            $customDocument = $request->file('custom_document')->store('campaign-media', media_disk());
            Log::warning('[CAMPAIGN] custom media stored', ['type' => 'document', 'path' => $customDocument]);
        }

        // Multi-engine: the unified picker posts a composite `engine:id` sender
        // key. Resolve it to the concrete sender id + engine so we persist the
        // engine the operator actually CHOSE (not just the workspace default).
        // When validated, set `provider` explicitly so the model's creating()
        // auto-stamp (which defaults to WorkspaceEngine::for()) is skipped. With
        // no sender key (legacy form) we leave device_id/provider on the old
        // path and the model auto-stamps the default engine as before.
        $wsId = $request->user()->current_workspace_id;
        $pickedDeviceId = $request->filled('device_id') ? (int) $request->input('device_id') : null;
        $pickedProvider = null;
        if ($request->filled('sender')) {
            $picked = \App\Services\WorkspaceEngine::senderForKey($wsId, $request->input('sender'));
            if ($picked) {
                $pickedDeviceId = (int) $picked['id'];
                $pickedProvider = (string) $picked['engine'];
            }
        }
        // Bare device_id with no composite sender key (REST API / legacy form):
        // a `devices` row is ALWAYS the Unofficial (Baileys) channel, so pin the
        // provider to Baileys. Without this the model's creating() auto-stamps
        // the workspace DEFAULT engine (e.g. Twilio) and the campaign sends on
        // the wrong channel even though a Baileys device was chosen.
        if ($pickedProvider === null && $pickedDeviceId) {
            $pickedProvider = \App\Services\WorkspaceEngine::ENGINE_BAILEYS;
        }

        // Twilio + WABA can't carry inline buttons on a CUSTOM (non-template)
        // send — Twilio requires a Content template, and WABA free-form
        // interactive only works inside the 24h window. Drop them so they're
        // never stored or shipped on those engines (the composer hides the
        // Buttons section client-side too, so this is the server backstop).
        if (in_array($pickedProvider, ['twilio', 'waba'], true)) {
            $request->merge(['custom_buttons' => [], 'custom_quick_replies' => []]);
        }

        $campaign = WpCampaign::create([
            'workspace_id'         => $request->user()->current_workspace_id,
            'campaign_name'        => $request->input('campaign_name'),
            'device_id'            => $pickedDeviceId,
            'provider'             => $pickedProvider,
            'campaign_type'        => $request->input('campaign_type'),
            'status'               => $request->input('status') ?: $resolvedStatus,
            'ab_testing'           => (bool) $request->boolean('ab_testing'),
            'ab_split'             => (int) ($request->input('ab_split') ?? 50),
            'custom_message'       => $request->input('custom_message'),
            'custom_message_b'     => $request->input('custom_message_b'),
            'custom_header'        => $request->input('custom_header'),
            'custom_footer'        => $request->input('custom_footer'),
            'custom_buttons'       => $request->input('custom_buttons'),
            'custom_quick_replies' => $request->input('custom_quick_replies'),
            // Persist the {{1}}→attribute slot map so SCHEDULED/RECURRING sends
            // (which fire later from the row) resolve positional vars too.
            'custom_variable_map'  => $request->input('custom_message_variable_map'),
            'custom_image'         => $customImage,
            'custom_video'         => $customVideo,
            'custom_document'      => $customDocument,
            'template_id'          => $request->input('template_id'),
            'template_id_a'        => $request->input('template_id_a'),
            'template_id_b'        => $request->input('template_id_b'),
            'flow_id'              => $request->input('flow_id'),
            'flow_id_b'            => $request->input('flow_id_b'),
            'use_attributes'       => (bool) $request->boolean('use_attributes'),
            'tracking_enabled'     => $request->has('tracking_enabled') ? (bool) $request->boolean('tracking_enabled') : true,
            'schedule_type'        => $scheduleType,
            'send_date'            => $request->input('send_date'),
            'send_time'            => $request->input('send_time'),
            // Never store a bare UTC for a local workspace — the active-hours
            // window + scheduling are interpreted in THIS timezone, so fall back
            // to the workspace's own tz (then the app default) when none is sent.
            'timezone'             => $request->input('timezone')
                ?: (optional($request->user()?->currentWorkspace)->timezone ?: config('app.timezone', 'UTC')),
            // Recurring cadence — only meaningful when schedule_type=recurring.
            'repeat_interval'      => $scheduleType === 'recurring' ? ($request->input('repeat_interval') ?: 'weekly') : null,
            'repeat_until'         => $scheduleType === 'recurring' ? $request->input('repeat_until') : null,
            // Smart Delivery (anti-ban) — null when left blank => global default.
            'throttle_min_sec'     => $request->filled('throttle_min_sec') ? (int) $request->input('throttle_min_sec') : null,
            'throttle_max_sec'     => $request->filled('throttle_max_sec') ? (int) $request->input('throttle_max_sec') : null,
            'batch_size'           => $request->filled('batch_size') ? (int) $request->input('batch_size') : null,
            'batch_pause_min'      => $request->filled('batch_pause_min') ? (int) $request->input('batch_pause_min') : null,
            'daily_limit'          => $request->filled('daily_limit') ? (int) $request->input('daily_limit') : null,
            'window_start'         => $request->filled('window_start') ? substr((string) $request->input('window_start'), 0, 5) : null,
            'window_end'           => $request->filled('window_end') ? substr((string) $request->input('window_end'), 0, 5) : null,
            'total_recipients'     => $totalRecipients,
            'created_by'           => optional($request->user())->id,
        ]);

        // Pre-create per-recipient log rows so the detail page has something
        // to render. Each row starts in 'queued' status. When A/B testing is
        // on, assign each recipient a variant ('A'/'B') by ab_split (% to A)
        // — shuffled so the split is random but honours the exact ratio, and
        // PERSISTED so resume passes keep the same assignment.
        $abOn    = (bool) $campaign->ab_testing;
        $abSplit = max(0, min(100, (int) ($campaign->ab_split ?? 50)));
        $allIds  = collect($contactIds)->all();
        $variantMap = [];
        if ($abOn) {
            $shuffled = $allIds;
            shuffle($shuffled);
            $countA = (int) round(count($shuffled) * $abSplit / 100);
            foreach ($shuffled as $i => $cid) {
                $variantMap[$cid] = $i < $countA ? 'A' : 'B';
            }
        }
        foreach ($allIds as $cid) {
            WpCampaignContact::create([
                'campaign_id' => $campaign->id,
                'contact_id'  => $cid,
                'status'      => 'queued',
                'variant'     => $abOn ? ($variantMap[$cid] ?? 'A') : null,
            ]);
        }

        // Hand the campaign off to the dispatcher. For schedule_type='now'
        // we fire immediately; scheduled / recurring stay queued until a
        // worker picks them up (cron worker not yet wired — they'll show
        // as 'scheduled' in the UI).
        // CHECKPOINT: if you see THIS line but never "dispatchCampaignNow queued",
        // the `if ($scheduleType==='now')` branch isn't being taken. If you DON'T
        // see this line at all (only "recipients resolved"), the request aborts
        // between recipient-resolution and here — OR the new code isn't live
        // (deploy + RELOAD PHP-FPM: optimize:clear does NOT clear opcache).
        Log::info('[CAMPAIGN] store complete — about to dispatch', [
            'campaign_id'   => $campaign->id,
            'schedule_type' => $scheduleType,
            'device_id'     => $campaign->device_id,
            'recipients'    => is_countable($contactIds) ? count($contactIds) : null,
        ]);
        if ($scheduleType === 'now') {
            $this->dispatchCampaignNow($campaign, $contactIds, $request->input('campaign_type'), [
                'template_id'          => $request->input('template_id'),
                'custom_message'       => $request->input('custom_message'),
                'custom_header'        => $request->input('custom_header'),
                'custom_footer'        => $request->input('custom_footer'),
                'custom_buttons'       => $request->input('custom_buttons'),
                'custom_quick_replies' => $request->input('custom_quick_replies'),
                'custom_variable_map'  => $request->input('custom_message_variable_map'),
            ]);
        }

        $message = match ($scheduleType) {
            'now'       => 'Campaign launched.',
            'recurring' => 'Recurring campaign saved.',
            default     => 'Campaign scheduled.',
        };

        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => $message,
                'campaign' => $campaign,
                'redirect' => route('user.wa-campaigns.detail', $campaign->id),
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $message);
    }

    /**
     * Normalize a custom-campaign body's placeholders to POSITIONAL and
     * (re)build its flat {slot => key} variable map.
     *
     * The composer inserts NAMED tokens ({{order_id}}) for readability;
     * this converts them to {{1}}, {{2}}… in first-appearance order and
     * returns a JSON map { "1":"order_id" } so storage matches the
     * template path. AttributeResolver already resolves both shapes, so
     * this only affects what we persist, not what gets sent.
     *
     * Idempotency / back-compat:
     *   - If the body has NO named token (it's empty or already positional
     *     {{1}}), return it unchanged together with the ORIGINAL map JSON —
     *     never renumber an existing positional body or clobber its map.
     *   - Mixed bodies: named tokens get key = token name; a bare numeric
     *     token (a generic {{1}} chip) has no attribute identity and maps
     *     to the literal number at its first-appearance slot.
     *
     * @param  string $body     raw custom_message
     * @param  string $mapJson  raw custom_message_variable_map (flat JSON)
     * @return array{0:string,1:string}  [normalizedBody, normalizedMapJson]
     */
    private function normalizeCustomMessage(string $body, string $mapJson): array
    {
        if ($body === '' || preg_match('/\{\{\s*[a-zA-Z_][\w.-]*\s*\}\}/u', $body) !== 1) {
            // No named tokens → leave body + map exactly as submitted.
            return [$body, $mapJson];
        }

        // First-appearance order → {token => slot}. Numeric and named
        // tokens share one sequence (matches the template normalizer).
        $order   = [];   // token => slot(int)
        $map     = [];   // slot(string) => key
        $newBody = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_][\w.-]*)\s*\}\}/u',
            function ($m) use (&$order, &$map) {
                $token = $m[1];
                if (!isset($order[$token])) {
                    $slot = count($order) + 1;
                    $order[$token] = $slot;
                    // Named token → key is the name; bare numeric chip →
                    // literal number (unmapped slot).
                    $map[(string) $slot] = $token;
                }
                return '{{' . $order[$token] . '}}';
            },
            $body
        );

        return [(string) $newBody, json_encode((object) $map, JSON_UNESCAPED_SLASHES)];
    }

    /**
     * Mirrors BroadcastsController::mediaUrlReachableForMeta — Meta
     * cannot fetch http://, private IPs, or .local/.test hosts. Pre-
     * flight here so a campaign with media-header doesn't burn quota
     * + credits 1-by-1 with #131053 before the operator sees the
     * failure.
     */
    private function mediaUrlReachableForMeta(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) return "Media URL '$url' is invalid.";
        $scheme = strtolower($parts['scheme'] ?? 'http');
        if ($scheme !== 'https') {
            return "Media URL must be HTTPS for Meta to fetch it (got: {$scheme}). Configure APP_URL with an https:// public domain.";
        }
        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPrivate = !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($isPrivate) return "Media URL host '$host' is a private/reserved IP. Meta cannot reach it.";
        } else {
            foreach (['.local', '.test', '.internal', '.localhost'] as $bad) {
                if (str_ends_with($host, $bad)) return "Media URL host '$host' ends with $bad which Meta cannot resolve.";
            }
            if ($host === 'localhost') return "Media URL host is 'localhost'. Meta cannot reach it.";
        }
        return null;
    }

    private function parseManualNumbers(string $raw): \Illuminate\Support\Collection
    {
        if (trim($raw) === '') return collect();
        return collect(preg_split('/[\s,;]+/', $raw))
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values();
    }

    private function parseCsvNumbers($file): \Illuminate\Support\Collection
    {
        $out = collect();
        if (!$file) return $out;
        $handle = @fopen($file->getRealPath(), 'r');
        if (!$handle) return $out;
        $headers = null;
        $row = 0;
        while (($cols = fgetcsv($handle)) !== false) {
            $row++;
            if ($row === 1) {
                // Detect headers — if first row contains any letters
                // outside digits/+/- assume it's a header row.
                $headers = array_map(fn ($c) => strtolower(trim((string) $c)), $cols);
                $hasHeader = false;
                foreach ($cols as $c) {
                    if (preg_match('/^(name|phone|mobile|number|contact)$/i', trim((string) $c))) {
                        $hasHeader = true; break;
                    }
                }
                if ($hasHeader) continue;
                $headers = null;
            }
            // Find phone column — by header name if available, else first
            // column that looks digit-y.
            $phoneIdx = 0;
            if ($headers) {
                foreach (['phone', 'mobile', 'number'] as $key) {
                    $idx = array_search($key, $headers, true);
                    if ($idx !== false) { $phoneIdx = $idx; break; }
                }
            }
            $phone = trim((string) ($cols[$phoneIdx] ?? ''));
            if ($phone !== '') $out->push($phone);
        }
        fclose($handle);
        return $out;
    }

    /**
     * Resolve the message body for a campaign — picks template body
     * (with `{{name}}` per-contact + `{{promo_key}}` workspace
     * substitution) or the custom_message field. Returned body is
     * per-contact since {{name}} differs per row.
     */
    private function resolveCampaignBody(Contact $contact, string $type, array $payload, int $workspaceId = 0): string
    {
        // Body resolution rules:
        //   - template type → use the template's stored template_body verbatim.
        //     Templates already have their own structure baked in; the campaign's
        //     custom_header / custom_footer are NOT applied (they belong to
        //     text/custom/button types).
        //   - everything else → use custom_message. Bold header is prepended,
        //     but the footer is NOT appended here — the dispatcher passes
        //     `footer` as a separate Baileys field, which is the native slot
        //     under the buttons. Appending it here would render it twice
        //     on the recipient's screen.
        $tpl = null;
        if ($type === 'template' && !empty($payload['template_id'])) {
            $tpl  = \App\Models\WaTemplate::query()->find($payload['template_id']);
            $full = (string) ($tpl?->template_body ?? '');
        } else {
            // Header/footer travel as separate Baileys fields (title +
            // footer slots) — see WhatsAppDispatcher::mergeButtonsFooter.
            // We don't bold-prepend them to the body here; Baileys will
            // render them in the proper UI slots above and below.
            $full = (string) ($payload['custom_message'] ?? '');
        }

        // Workspace-attribute substitution first ({{promo_key}}, {{order_id}},
        // positional {{1}} via variable_map). This is what /team-inbox does
        // — and matches the AttributeResolver pass on dispatcher paths so
        // operators get consistent behaviour everywhere.
        if ($workspaceId > 0) {
            // Template campaigns carry their slot→attribute mapping on the
            // template row. CUSTOM campaigns carry it in the composer's
            // `custom_message_variable_map` (the `/`-picker's {{1}}→key map),
            // threaded through here as `custom_variable_map`. Pick whichever
            // applies so positional {{1}} slots resolve to real values on
            // BOTH paths — and custom sends never ship a literal {{1}}.
            $variableMap = $tpl ? $tpl->variable_map : ($payload['custom_variable_map'] ?? null);
            if (is_string($variableMap)) {
                $decoded = json_decode($variableMap, true);
                $variableMap = is_array($decoded) ? $decoded : [];
            }
            $variableMap = is_array($variableMap) ? $variableMap : [];
            $full = app(\App\Services\AttributeResolver::class)->resolve($full, $variableMap, $workspaceId);
        }

        // Per-contact substitution second: {{name}}, {{first_name}}, etc.
                //
        // Tolerant matcher — accepts ANY of:
        //   {{first_name}}  {{First Name}}  {{FIRST NAME}}  {{ first name }}
        // and falls back to the contact's own custom_attributes JSON when
        // the placeholder name isn't a built-in field. That way an operator
        // can write `Hey {{First Name}}, your code is {{Promo Code}}` in the
        // composer (the natural reading form) without having to map every
        // placeholder to a numbered {{1}}/{{2}} slot first. We previously
        // only matched exact lowercase snake_case keys (`{{first_name}}`),
        // so the screenshot's `{{First Name}}` / `{{Promo Code}}` / etc.
        // shipped as literal text.
        // Combined / display name — falls back to first+last when contact.name
        // is blank (some import paths populate parts but not the joined column).
        $combinedName = (string) ($contact->name
            ?? trim(((string) ($contact->first_name ?? '')) . ' ' . ((string) ($contact->last_name ?? '')))
        );

        $stdFields = [
            'name'         => $combinedName,
            'full_name'    => $combinedName,                              // alias — the natural form
            'fullname'     => $combinedName,                              // alias (no-space variant)
            'display_name' => $combinedName,                              // alias (some clients use this)
            'first_name'   => (string) ($contact->first_name ?? ''),
            'firstname'    => (string) ($contact->first_name ?? ''),     // alias
            'last_name'    => (string) ($contact->last_name ?? ''),
            'lastname'     => (string) ($contact->last_name ?? ''),      // alias
            'mobile'       => (string) ($contact->mobile ?? ''),
            'phone'        => (string) ($contact->mobile ?? ''),
            'email'        => (string) ($contact->email ?? ''),
            'address'      => (string) ($contact->address ?? ''),
            'language'     => (string) ($contact->language ?? ''),
            'title'        => (string) ($contact->title ?? ''),
            'country_code' => (string) ($contact->country_code ?? ''),
        ];
        // Per-contact custom_attributes (the JSON column populated when the
        // operator adds free-form key/value pairs on /contacts). Casefolded
        // + space-stripped so placeholder text matches regardless of casing.
        $customAttrs = $contact->custom_attributes;
        if (is_string($customAttrs)) {
            $d = json_decode($customAttrs, true);
            $customAttrs = is_array($d) ? $d : [];
        }
        $customAttrs = is_array($customAttrs) ? $customAttrs : [];

        // Build a lookup that normalises every key (spaces → underscores,
        // lowercased, surrounding whitespace trimmed) so the placeholder
        // form the operator wrote can be matched in one pass.
        $normalisedLookup = [];
        foreach ($stdFields as $k => $v) {
            $normalisedLookup[strtolower(str_replace(' ', '_', $k))] = (string) $v;
        }
        foreach ($customAttrs as $k => $v) {
            $key = strtolower(str_replace(' ', '_', (string) $k));
            if (! isset($normalisedLookup[$key])) {
                $normalisedLookup[$key] = is_scalar($v) ? (string) $v : '';
            }
        }

        // Single regex pass over every {{anything}} in the body. Skips pure
        // numeric tokens like {{1}} / {{2}} — those are positional slots
        // handled by the variable_map block below, not by-name substitution.
        $full = (string) preg_replace_callback(
            '/\{\{\s*([^{}]+?)\s*\}\}/u',
            function ($m) use ($normalisedLookup) {
                $rawKey = trim($m[1]);
                if ($rawKey === '' || preg_match('/^\d+$/', $rawKey)) {
                    return $m[0]; // leave {{N}} for the positional pass below
                }
                $key = strtolower(str_replace(' ', '_', $rawKey));
                if (array_key_exists($key, $normalisedLookup)) {
                    return $normalisedLookup[$key];
                }
                return $m[0]; // unknown placeholder — preserve so operator sees it
            },
            $full
        );

        // Positional {{N}} → the attribute the operator mapped this slot to in
        // the template's variable_map → THIS contact's value. AttributeResolver
        // above already filled slots mapped to WORKSPACE attributes; this fills
        // slots mapped to CONTACT fields / per-contact custom attributes (which
        // the workspace resolver can't see). This is the campaign-path twin of
        // BroadcastsController::varsForRecipient — so {{1}}/{{2}} personalize
        // identically whether the operator assigns them a workspace OR contact
        // attribute. The literal {{N}} stays in template_body for Meta.
        // Source the slot map from the template (nested header/body shape)
        // OR, for a CUSTOM campaign, from the composer's flat
        // {"1":"first_name"} map threaded in via custom_variable_map.
        $vmSource = $tpl ? $tpl->variable_map : ($payload['custom_variable_map'] ?? null);
        if ($vmSource && str_contains($full, '{{')) {
            $vm = $vmSource;
            if (is_string($vm)) {
                $d = json_decode($vm, true);
                $vm = is_array($d) ? $d : [];
            }
            $flat = [];
            if (is_array($vm)) {
                if ($tpl) {
                    // Template: nested ['header'=>[{num,key}], 'body'=>[…]].
                    foreach (['header', 'body'] as $sec) {
                        foreach ((array) ($vm[$sec] ?? []) as $e) {
                            if (is_array($e) && isset($e['num'], $e['key'])) {
                                $flat[(string) $e['num']] = (string) $e['key'];
                            }
                        }
                    }
                } else {
                    // Custom: already flat {slot => key} from the `/`-picker.
                    foreach ($vm as $slot => $key) {
                        if (is_string($key) || is_numeric($key)) {
                            $flat[(string) $slot] = (string) $key;
                        }
                    }
                }
            }
            if ($flat) {
                $custom = $contact->custom_attributes;
                if (is_string($custom)) {
                    $d = json_decode($custom, true);
                    $custom = is_array($d) ? $d : [];
                }
                $custom = is_array($custom) ? $custom : [];
                $std = [
                    'name'       => $contact->name,
                    'first_name' => $contact->first_name,
                    'last_name'  => $contact->last_name,
                    'mobile'     => $contact->mobile,
                    'phone'      => $contact->mobile,
                    'email'      => $contact->email,
                    'address'    => $contact->address ?? null,
                    'title'      => $contact->title ?? null,
                ];
                foreach ($flat as $slot => $key) {
                    $val = array_key_exists($key, $std) && $std[$key] !== null
                        ? (string) $std[$key]
                        : (array_key_exists($key, $custom) ? (string) $custom[$key] : null);
                    if ($val !== null && $val !== '') {
                        $full = preg_replace('/\{\{\s*' . preg_quote((string) $slot, '/') . '\s*\}\}/', $val, $full);
                    }
                }
            }
        }

        return $full;
    }

    /**
     * Public entry for a "send now" campaign. Hands the actual recipient loop
     * off to run AFTER the HTTP response is flushed, so the request returns
     * instantly instead of the browser hanging while the paced loop sleeps
     * between recipients (msg_gap can be a minute or more). The anti-ban gap
     * can only work if we don't block the request — the pacing itself lives in
     * runCampaignNowPaced().
     */
    private function dispatchCampaignNow(WpCampaign $campaign, $contactIds, string $type, array $payload): void
    {
        // Diagnostic: this line proves store() reached the dispatch. The send
        // itself is deferred to AFTER the HTTP response (afterResponse) so the
        // page returns instantly. If you see THIS log but never "afterResponse
        // fired" below, the server isn't running terminating callbacks for this
        // request (PHP-FPM request_terminate_timeout / proxy buffering / Octane)
        // — in which case we fall back to a synchronous run.
        Log::info('[CAMPAIGN] dispatchCampaignNow queued (afterResponse)', [
            'campaign_id' => $campaign->id,
            'recipients'  => is_countable($contactIds) ? count($contactIds) : null,
        ]);

        $run = function () use ($campaign, $contactIds, $type, $payload) {
            @set_time_limit(0);
            @ignore_user_abort(true);
            Log::info('[CAMPAIGN] afterResponse fired — running paced send', ['campaign_id' => $campaign->id]);
            try {
                $this->runCampaignNowPaced($campaign, $contactIds, $type, $payload);
            } catch (\Throwable $e) {
                Log::error('[CAMPAIGN] runCampaignNowPaced threw', [
                    'campaign_id' => $campaign->id,
                    'err'         => $e->getMessage(),
                    'at'          => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        };

        // Prefer after-response (instant page). If the runtime can't defer
        // (no fastcgi_finish_request — e.g. some CLI/proxy setups), run inline
        // so the campaign ALWAYS sends instead of silently never dispatching.
        if (function_exists('fastcgi_finish_request')) {
            dispatch($run)->afterResponse();
        } else {
            Log::warning('[CAMPAIGN] no fastcgi_finish_request — running send inline', ['campaign_id' => $campaign->id]);
            $run();
        }
    }

    /**
     * Record a TRANSIENT send failure (network/provider/Node-down) with
     * bounded exponential backoff. While attempts remain, the row stays
     * non-terminal with a future next_attempt_at so a later sweeper pass
     * retries it; only once the cap is hit does it become a terminal
     * failure that counts toward failed_count. failed_count is therefore
     * incremented exactly once per recipient (on final give-up), never per
     * retry.
     */
    private function recordSendFailure(?WpCampaignContact $logRow, WpCampaign $campaign, string $errMsg, int $maxAttempts, int $retryBackoff): void
    {
        $err = mb_substr($errMsg, 0, 191);
        if (! $logRow) {
            $campaign->increment('failed_count');
            return;
        }
        $attempts = (int) ($logRow->send_attempts ?? 0) + 1;
        if ($attempts < $maxAttempts) {
            // Exponential backoff: base, base*2, base*4, … capped at 1 hour.
            $delay = min(3600, $retryBackoff * (2 ** ($attempts - 1)));
            $logRow->update([
                'status'          => 'failed',
                'send_attempts'   => $attempts,
                'next_attempt_at' => now()->addSeconds($delay),
                'error_message'   => $err,
            ]);
            return;
        }
        // Retries exhausted — terminal failure.
        $logRow->update([
            'status'          => 'failed',
            'send_attempts'   => $attempts,
            'next_attempt_at' => null,
            'error_message'   => $err,
        ]);
        $campaign->increment('failed_count');
    }

    /**
     * Record a PERMANENT failure that retrying can't fix (bad number, empty
     * body, plan cap reached). Stamp send_attempts at the cap so the resume
     * loop treats it as terminal — never re-attempted, never counted as
     * "retryable" (which would stop the campaign ever completing).
     */
    private function recordPermanentFailure(?WpCampaignContact $logRow, WpCampaign $campaign, string $errMsg, int $maxAttempts): void
    {
        $campaign->increment('failed_count');
        $logRow?->update([
            'status'          => 'failed',
            'send_attempts'   => $maxAttempts,
            'next_attempt_at' => null,
            'error_message'   => mb_substr($errMsg, 0, 191),
        ]);
    }

    /**
     * Iterate the campaign's recipients and fire each through the
     * dispatcher's `sendRaw` API, pacing between sends with the admin's
     * msg_gap / batch settings. NO rows written to `conversations`
     * or `messages` — campaign data lives entirely in `wp_campaigns`
     * + `wp_campaign_contacts`. The chat tables (`conversations` +
     * `messages`) stay clean and are only ever touched by /chat.
     *
     * Failures get logged into the WpCampaignContact row's
     * `status` / `error_message` columns.
     */
    private function runCampaignNowPaced(WpCampaign $campaign, $contactIds, string $type, array $payload): void
    {
        // Never message a contact who opted out (STOP keyword or the manual
        // unsubscribe toggle). Keep false + null (never-set); drop only
        // explicit unsubscribes. This is the binding compliance filter — it
        // runs for both immediate and scheduled campaigns (both land here).
        $contacts = Contact::query()->whereIn('id', $contactIds)
            ->where(function ($q) {
                $q->where('is_unsubscribed', false)->orWhereNull('is_unsubscribed');
            })
            ->get();
        // Resolve the owner from the CAMPAIGN row, NOT the auth session. This
        // method also runs from the Node-heartbeat sweep (fireScheduledCampaign)
        // where there is no logged-in user — Auth::id() would be null and the
        // device + workspace scoping below would then resolve to nothing
        // ("No connected device"). The campaign always carries its own owner.
        $userId   = $campaign->user_id ?: Auth::id();

        Log::info('[CAMPAIGN] dispatchCampaignNow start', [
            'campaign_id' => $campaign->id,
            'name'        => $campaign->campaign_name,
            'type'        => $type,
            'recipients'  => $contacts->count(),
            'device_id'   => $campaign->device_id,
            'template_id' => $payload['template_id'] ?? null,
        ]);

        // Flow campaigns aren't a body-send — each recipient gets a new
        // flow session spun up by the Node bridge. Hand off to the
        // dedicated dispatcher so the body-build + dispatcher->sendRaw
        // pipeline below stays for text/template/button/media/custom
        // where the body is the message itself.
        if ($type === 'flow') {
            $this->dispatchFlowCampaign($campaign, $contacts, $userId);
            return;
        }

        // Sender phone — read once from the device picked in step 1.
        $devicePhone = null;
        if ($campaign->device_id) {
            // Scope by the CAMPAIGN's workspace/owner (forWorkspace), not
            // forCurrentWorkspace() which reads the auth session the sweep
            // doesn't have. forWorkspace() also falls back to user ownership
            // for legacy rows whose workspace_id was never stamped.
            $device = \App\Models\Device::query()
                ->forWorkspace($campaign->workspace_id, $campaign->user_id)
                ->find($campaign->device_id);
            // The campaign's device_id is an explicit, stored choice — it is
            // authoritative. If workspace-scoping can't see it (e.g. a device
            // paired before devices.workspace_id was populated → NULL, and the
            // campaign's user_id is also NULL so the ownership fallback misses)
            // do a direct lookup. Otherwise we'd pass a NULL sender and let the
            // dispatcher backfill the workspace's PRIMARY engine number — which
            // for a multi-engine workspace can be a totally different channel.
            if (! $device) {
                $device = \App\Models\Device::query()->find($campaign->device_id);
            }
            if ($device) {
                $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null;
            }
        }

        // Buttons + footer + header per Baileys interactive-message
        // schema. For template campaigns we read from the template's
        // structured columns so the copy_code / visit_website / etc.
        // types are preserved. For custom/button/text we use the
        // campaign-create form's basic single-button override.
        //
        // Workspace-attribute resolution ({{promo_key}} → "Media City",
        // {{order_id}} → "ORD-12", positional {{1}} via variable_map)
        // happens HERE once, before the per-contact loop — those values
        // are constant across recipients. Contact-level placeholders
        // ({{name}}, {{email}}, …) are substituted later inside
        // resolveCampaignBody per-row.
        $isTemplate = $type === 'template';

        // Rich CUSTOM-campaign media — resolve which (if any) column holds an
        // uploaded file. The column that's set encodes the type. This rides
        // the legacy/sendRaw (Unofficial API) path only; the WABA
        // TemplateSender fast-path below handles its own media headers, so
        // template campaigns skip this. Single media per campaign, image first.
        $mediaPath = $mediaType = null;
        if (!$isTemplate) {
            if (!empty($campaign->custom_image)) {
                $mediaPath = $campaign->custom_image; $mediaType = 'image';
            } elseif (!empty($campaign->custom_video)) {
                $mediaPath = $campaign->custom_video; $mediaType = 'video';
            } elseif (!empty($campaign->custom_document)) {
                $mediaPath = $campaign->custom_document; $mediaType = 'document';
            }
            if ($mediaPath) {
                Log::warning('[CAMPAIGN] custom media will ride sendRaw', [
                    'campaign_id' => $campaign->id, 'media_type' => $mediaType, 'media_path' => $mediaPath,
                ]);
            }
        }

        $tplCache = null;
        if ($isTemplate && !empty($payload['template_id'])) {
            $tplCache = \App\Models\WaTemplate::query()->find($payload['template_id']);
        }

        // TEMPLATE campaign on the sendRaw (Unofficial API) fallback path:
        // WABA-approved templates use TemplateSender below (own media header),
        // but a NON-approved / Unofficial template still rides sendRaw — so
        // carry the template's HEADER media here or the image ships as text-only.
        // attachment_file is the public-disk relative path ('wa-templates/<file>')
        // which the dispatcher base64-inlines just like custom media.
        if ($isTemplate && $tplCache && !$mediaPath
            && !empty($tplCache->attachment_file)
            && in_array($tplCache->attachment_type, ['image', 'video', 'document'], true)) {
            $mediaPath = $tplCache->attachment_file;
            $mediaType = $tplCache->attachment_type;
            Log::warning('[CAMPAIGN] template header media will ride sendRaw fallback', [
                'campaign_id' => $campaign->id, 'media_type' => $mediaType, 'media_path' => $mediaPath,
            ]);
        }

        $wsId = (int) ($campaign->workspace_id ?? Auth::user()->current_workspace_id ?? 0);
        $resolver = app(\App\Services\AttributeResolver::class);
        $variableMap = $tplCache?->variable_map;
        if (is_string($variableMap)) {
            $decoded = json_decode($variableMap, true);
            $variableMap = is_array($decoded) ? $decoded : [];
        }
        $variableMap = is_array($variableMap) ? $variableMap : [];

        $headerRaw = $isTemplate ? ($tplCache?->header ?: null) : ($payload['custom_header'] ?? null);
        $footerRaw = $isTemplate ? ($tplCache?->footer ?: null) : ($payload['custom_footer'] ?? null);
        $headerResolved = $headerRaw ? $resolver->resolve((string) $headerRaw, $variableMap, $wsId) : null;
        $footerResolved = $footerRaw ? $resolver->resolve((string) $footerRaw, $variableMap, $wsId) : null;

        // Resolve button labels — operator can drop {{promo_key}} into a
        // button's `text` field too (used for "Use code XYZ" CTAs).
        $resolveButtons = function ($buttons) use ($resolver, $variableMap, $wsId) {
            if (!is_array($buttons)) return $buttons;
            return array_map(function ($b) use ($resolver, $variableMap, $wsId) {
                if (!is_array($b)) return $b;
                foreach (['text', 'title', 'url'] as $f) {
                    if (isset($b[$f]) && is_string($b[$f])) {
                        $b[$f] = $resolver->resolve($b[$f], $variableMap, $wsId);
                    }
                }
                return $b;
            }, $buttons);
        };

        $extras = array_filter([
            'buttons' => $resolveButtons($isTemplate
                ? ($tplCache && is_array($tplCache->buttons) ? $tplCache->buttons : null)
                : ($payload['custom_buttons'] ?? null)),
            'quick_replies' => $resolveButtons($payload['custom_quick_replies'] ?? null),
            'footer' => $footerResolved,
            'header' => $headerResolved,
            // Carousel cards for the Unofficial-API legacy path — without these
            // a carousel-type template campaign on a non-WABA workspace ships
            // only the body and drops every card.
            'template_type' => ($isTemplate && $tplCache) ? ($tplCache->template_type ?: null) : null,
            'carousel_data' => ($isTemplate && $tplCache && $tplCache->template_type === 'carousel' && !empty($tplCache->carousel_data))
                ? $tplCache->carousel_data : null,
        ], fn ($v) => !empty($v));

        // ==== A/B testing — build the Variant B content bundle ONCE ==========
        // Snapshot the Variant A bundle (above) and, when A/B is on, build the
        // parallel Variant B bundle. The per-recipient loop just re-points the
        // working vars to A or B by the contact's assigned `variant` — the send
        // logic itself is untouched. Template campaigns swap template_id_a→_b
        // (different template + its own buttons/header/carousel); custom-text
        // campaigns swap the body to custom_message_b (media/buttons shared).
        $abOn       = (bool) $campaign->ab_testing;
        $tplCacheA  = $tplCache;   $extrasA = $extras;
        $mediaPathA = $mediaPath;  $mediaTypeA = $mediaType;  $payloadA = $payload;
        $tplCacheB  = $tplCache;   $extrasB = $extras;
        $mediaPathB = $mediaPath;  $mediaTypeB = $mediaType;  $payloadB = $payload;
        if ($abOn) {
            if ($isTemplate && !empty($campaign->template_id_b)) {
                $tplCacheB = \App\Models\WaTemplate::query()->find($campaign->template_id_b);
                $payloadB  = array_merge($payload, ['template_id' => $campaign->template_id_b]);
                // Variant B header media (sendRaw fallback path), mirroring A.
                $mediaPathB = $mediaTypeB = null;
                if ($tplCacheB && !empty($tplCacheB->attachment_file)
                    && in_array($tplCacheB->attachment_type, ['image', 'video', 'document'], true)) {
                    $mediaPathB = $tplCacheB->attachment_file;
                    $mediaTypeB = $tplCacheB->attachment_type;
                }
                // Variant B extras (buttons/footer/header/carousel) from tplCacheB.
                $vmapB = $tplCacheB?->variable_map;
                if (is_string($vmapB)) { $dB = json_decode($vmapB, true); $vmapB = is_array($dB) ? $dB : []; }
                $vmapB = is_array($vmapB) ? $vmapB : [];
                $hdrB  = $tplCacheB?->header ?: null;
                $ftrB  = $tplCacheB?->footer ?: null;
                $extrasB = array_filter([
                    'buttons'       => $resolveButtons($tplCacheB && is_array($tplCacheB->buttons) ? $tplCacheB->buttons : null),
                    'quick_replies' => $resolveButtons($payload['custom_quick_replies'] ?? null),
                    'footer'        => $ftrB ? $resolver->resolve((string) $ftrB, $vmapB, $wsId) : null,
                    'header'        => $hdrB ? $resolver->resolve((string) $hdrB, $vmapB, $wsId) : null,
                    'template_type' => $tplCacheB ? ($tplCacheB->template_type ?: null) : null,
                    'carousel_data' => ($tplCacheB && $tplCacheB->template_type === 'carousel' && !empty($tplCacheB->carousel_data))
                        ? $tplCacheB->carousel_data : null,
                ], fn ($v) => !empty($v));
            } elseif (!$isTemplate && trim((string) ($campaign->custom_message_b ?? '')) !== '') {
                $payloadB = array_merge($payload, ['custom_message' => $campaign->custom_message_b]);
            }
        }

        // Sender pacing — per-campaign "Smart Delivery" overrides win; otherwise
        // fall back to the platform-wide admin knobs (the SAME ones Node uses):
        // msg_gap (seconds between sends), enable_batches + batches_gap (batch
        // size), bw_msg_gap (minutes between batches). This loop runs after the
        // HTTP response (see dispatchCampaignNow), so sleeping here is what
        // actually spaces the messages out without hanging the request.
        $gapSec      = max(0, (int) \App\Models\SystemSetting::get('msg_gap', 3));
        $batchOn     = (bool) \App\Models\SystemSetting::get('enable_batches', false);
        $batchSize   = max(1, (int) \App\Models\SystemSetting::get('batches_gap', 50));
        $batchGapMin = max(0, (int) \App\Models\SystemSetting::get('bw_msg_gap', 5));

        // Durable auto-retry — a FAILED recipient is retried up to
        // $maxAttempts times with exponential backoff (base * 2^(n-1)),
        // instead of staying terminally failed after one try. The campaign
        // re-arms (status=scheduled) until every recipient is either
        // delivered or has exhausted its attempts; CampaignScheduleSweeper
        // resumes it on the next heartbeat. Set attempts=1 to disable.
        $maxAttempts   = max(1, (int) \App\Models\SystemSetting::get('campaign_retry_attempts', 3));
        $retryBackoff  = max(5, (int) \App\Models\SystemSetting::get('campaign_retry_backoff_sec', 60));

        // Per-campaign random delay window (throttle_min/max seconds). When both
        // are set and max>=min>0 we draw a FRESH random_int(min,max) per
        // recipient — a true per-user interval — instead of the global gap ±20%.
        $tMin = (int) ($campaign->throttle_min_sec ?? 0);
        $tMax = (int) ($campaign->throttle_max_sec ?? 0);
        $useRandomDelay = $tMin > 0 && $tMax >= $tMin;

        // Per-campaign batch overrides (size + pause). NULL => keep the global.
        if ((int) ($campaign->batch_size ?? 0) > 0) { $batchOn = true; $batchSize = max(1, (int) $campaign->batch_size); }
        if (($campaign->batch_pause_min ?? null) !== null) { $batchGapMin = max(0, (int) $campaign->batch_pause_min); }

        // Daily cap + active sending window (interpreted in the campaign's own
        // timezone). On hitting either, the run STOPS and re-arms via the
        // sweeper (see end of method) — no multi-hour FPM sleep.
        $dailyLimit  = (int) ($campaign->daily_limit ?? 0);
        $tz          = $campaign->timezone ?: config('app.timezone', 'UTC');
        $winStart    = $campaign->window_start ?: null;   // "HH:MM"
        $winEnd      = $campaign->window_end   ?: null;
        $sentThisRun = 0;
        $stopReason  = null;   // 'cap' | 'window' — drives re-arm vs complete

        $paceIdx = 0;

        // WhatsApp Warmer — per-NUMBER governor layered on the per-campaign
        // pacing above. When the sending number opted into warming, its ramped
        // daily budget + active hours + send-gap floor + spintax apply to EVERY
        // campaign from that number (the per-campaign knobs are per-blast).
        $warmer      = app(\App\Services\WarmerService::class);
        $warmProvider = strtolower((string) ($campaign->provider ?? ''));
        // Engine-aware governor. WABA / Twilio warm the SENDING wa_provider_configs
        // row (Meta still enforces tiers, but the ramp paces volume to protect the
        // quality rating); Unofficial warms its Device. Single-number campaigns
        // leave device_id NULL → fall back to the workspace's primary number for
        // that engine, so warming is never silently skipped.
        if (in_array($warmProvider, ['waba', 'twilio'], true)) {
            try {
                $warmDevice = \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->where('provider', $warmProvider)
                    ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                    ->when($campaign->device_id, fn ($q) => $q->where('id', $campaign->device_id))
                    ->orderByDesc('is_primary')->orderByDesc('connected_at')->first();
            } catch (\Throwable $e) { $warmDevice = null; }
        } else {
            $warmDevice = $device ?? null;
            if (!$warmDevice) {
                try {
                    $warmDevice = \App\Models\Device::query()
                            ->forWorkspace($campaign->workspace_id, $campaign->user_id)
                            ->where('status', 'connected')->orderByDesc('id')->first()
                        ?: \App\Models\Device::query()
                            ->forWorkspace($campaign->workspace_id, $campaign->user_id)
                            ->orderByDesc('id')->first();
                } catch (\Throwable $e) { $warmDevice = null; }
            }
        }
        $warmEnabled = $warmDevice && $warmer->enabled($warmDevice);

        // Wall-clock budget. This loop runs in afterResponse() (or the sweep
        // tick); PHP-FPM's request_terminate_timeout HARD-kills the worker after
        // ~30-120s regardless of set_time_limit(0). With a per-message delay,
        // delay × recipients easily exceeds that, so the worker dies mid-loop
        // and ONLY the first recipient(s) get sent. Cap each run: stop cleanly
        // before the kill and re-arm — CampaignScheduleSweeper resumes the rest
        // (idempotent: already-sent recipients are skipped).
        $runStart    = time();
        $maxRunSec   = 20;   // safely under typical FPM timeouts and the 25s sweep lock
        $resumeInSec = 0;    // pending gap to honour when the next chunk resumes

        foreach ($contacts as $contact) {
            $logRow = WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('contact_id', $contact->id)
                ->first();

            // Skip recipients already sent on a PRIOR run (idempotent resume +
            // crash-safety). A re-fired campaign — after a daily-cap/window
            // pause, or after the Node bridge restarted mid-run — never
            // double-sends to anyone already delivered.
            if ($logRow && in_array($logRow->status, ['sent', 'delivered', 'read', 'responded'], true)) {
                continue;
            }

            // Retry exhausted — this recipient hit the attempt cap (or was a
            // permanent/data failure stamped at the cap). Terminal: never
            // re-attempt, and it no longer counts toward "retryable" below.
            if ($logRow && (int) ($logRow->send_attempts ?? 0) >= $maxAttempts) {
                continue;
            }

            // Backoff not elapsed — a prior attempt failed and this row's
            // next_attempt_at is still in the future. Defer to a later run;
            // the campaign re-arms to the earliest due time at the end.
            if ($logRow && $logRow->next_attempt_at && now()->lt($logRow->next_attempt_at)) {
                continue;
            }

            // Active sending window — outside the allowed hours we stop and
            // re-arm to the next window open (covers "send only 9am–9pm").
            if ($winStart && $winEnd && !$this->withinSendWindow($tz, $winStart, $winEnd)) {
                $stopReason = 'window';
                break;
            }
            // Daily cap — once this run has sent its quota, stop and resume
            // tomorrow. This is what makes a 1000+ blast safe on the Unofficial
            // API (daily volume is the #1 ban driver).
            if ($dailyLimit > 0 && $sentThisRun >= $dailyLimit) {
                $stopReason = 'cap';
                break;
            }

            // WhatsApp Warmer — per-number governor. Over the number's ramped
            // daily budget or outside its active hours → stop + re-arm (the
            // sweeper resumes next window/day). Protects the NUMBER's reputation
            // across every campaign it sends.
            if ($warmEnabled) {
                $wm = $warmer->canSend($warmDevice);
                if (!$wm['ok']) { $stopReason = 'warmer'; break; }
            }

            // Space out sends: per-message gap before every recipient after the
            // first. Per-campaign throttle draws a fresh random delay in its
            // [min,max] window; otherwise the global gap ±20% jitter (so the
            // timing isn't fingerprint-uniform). Plus the longer batch gap every
            // $batchSize messages when batching is on.
            if ($paceIdx > 0) {
                // This recipient's gap: per-campaign random window, or the global
                // gap ±20% jitter; plus the batch pause when a batch closes.
                $thisGap = $useRandomDelay
                    ? random_int($tMin, $tMax)
                    : ($gapSec > 0 ? max(1, (int) round($gapSec * (1 + random_int(-20, 20) / 100))) : 0);
                if ($batchOn && $batchGapMin > 0 && ($paceIdx % $batchSize) === 0) {
                    $thisGap += $batchGapMin * 60;
                }
                // Warmer per-number gap floor — keep sends at least this far apart
                // regardless of the campaign's own (possibly faster) pacing.
                if ($warmEnabled) {
                    $thisGap = max($thisGap, $warmer->gapSeconds($warmDevice));
                }
                // Never sleep past the run budget — stop cleanly + re-arm instead
                // of risking an FPM hard-kill that strands the rest unsent.
                if ($thisGap > 0 && (time() - $runStart) + $thisGap > $maxRunSec) {
                    $stopReason  = 'time';
                    $resumeInSec = $thisGap;
                    break;
                }
                if ($thisGap > 0) {
                    sleep($thisGap);
                }
            }
            $paceIdx++;

            // A/B variant select — re-point ALL content inputs to this
            // recipient's assigned variant. Reset from the A/B snapshots every
            // iteration so an A after a B never inherits B's content. The send
            // logic below uses $tplCache/$payload/$extras/$mediaPath/$mediaType
            // unchanged — only their source flips here.
            $useB      = $abOn && $logRow && $logRow->variant === 'B';
            $tplCache  = $useB ? $tplCacheB  : $tplCacheA;
            $extras    = $useB ? $extrasB    : $extrasA;
            $mediaPath = $useB ? $mediaPathB : $mediaPathA;
            $mediaType = $useB ? $mediaTypeB : $mediaTypeA;
            $payload   = $useB ? $payloadB   : $payloadA;

            $to = $contact->mobile;
            Log::info('[CAMPAIGN] processing contact', [
                'campaign_id' => $campaign->id,
                'contact_id'  => $contact->id,
                'to'          => $to,
            ]);
            if (!$to) {
                Log::warning('[CAMPAIGN] skipping — no mobile', ['contact_id' => $contact->id]);
                $this->recordPermanentFailure($logRow, $campaign, 'No mobile number on contact', $maxAttempts);
                continue;
            }
            $body = $this->resolveCampaignBody($contact, $type, $payload, $wsId);
            // Warmer spintax — expand {a|b|c} for per-message variety so a blast
            // isn't byte-identical to every recipient (only when the number opted in).
            if ($warmEnabled) { $body = $warmer->applySpin($warmDevice, $body); }
            if (trim($body) === '') {
                $this->recordPermanentFailure($logRow, $campaign, 'Empty message body after template/variable resolution', $maxAttempts);
                continue;
            }
            // This recipient is a real send attempt — count it toward the daily
            // cap (bad-data skips above don't burn the quota).
            $sentThisRun++;

            // Plan-first billing — identical model to every other surface
            // (OverflowBilling, used by WhatsAppDispatcher + InboxDispatcher):
            // each send is FREE while the workspace is under its plan's
            // monthly_messages_limit, and only spends ONE wallet credit once the
            // plan quota is exhausted. NO wallet pre-gate: an active plan must
            // not be blocked by an empty wallet. $used = this workspace's
            // campaign sends already marked this calendar month; it grows as the
            // loop marks rows 'sent', so it self-tracks per recipient.
            try {
                $wsObj = \App\Models\Workspace::find($wsId);
                if ($wsObj) {
                    $usedThisMonth = WpCampaignContact::query()
                        ->whereIn('campaign_id', WpCampaign::where('workspace_id', $wsId)->pluck('id'))
                        ->whereIn('status', ['sent', 'delivered', 'read', 'responded'])
                        ->where('updated_at', '>=', now()->startOfMonth())
                        ->count();
                    // Campaigns are bulk outreach → bill at the recipient's
                    // country MARKETING rate (the safe/expensive tier; admin can
                    // tune per country). No-ops to flat when per-country is OFF.
                    \App\Services\OverflowBilling::consumeOne($wsObj, $usedThisMonth, $to, 'marketing');
                    Log::warning('[CAMPAIGN TRACE] billing ok (plan-first)', [
                        'campaign_id' => $campaign->id,
                        'contact_id'  => $contact->id,
                        'to'          => $to,
                        'used_month'  => $usedThisMonth,
                    ]);
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                Log::warning('[CAMPAIGN TRACE] billing BLOCKED — plan cap reached + wallet empty', [
                    'campaign_id' => $campaign->id,
                    'contact_id'  => $contact->id,
                    'to'          => $to,
                ]);
                $this->recordPermanentFailure($logRow, $campaign, 'Plan cap reached — top up wallet to keep sending', $maxAttempts);
                continue;
            }

            // FAST PATH — when this is a WABA template campaign AND
            // the template has been submitted to Meta + approved, send
            // through TemplateSender. It builds the FULL Meta payload
            // (buttons / carousel / media headers / auth OTP), wraps
            // URLs via LinkTracker, runs ban-prevention gates, fires
            // outbound webhooks. Bypasses the legacy sendRaw path
            // which only built header+body text params and dropped
            // every button / carousel / media header silently.
            //
            // IMPORTANT: when TemplateSender path is selected, we COMMIT
            // to it. An exception → mark failed + refund + continue,
            // NOT fall through to dispatcher->sendRaw which would
            // double-charge AND send a degraded text-only message.
            // Multi-engine: this fast-path sends via Meta Cloud (WABA). Only take
            // it when the campaign is actually on WABA — otherwise a campaign the
            // operator pinned to the Unofficial API / Twilio whose template ALSO
            // happens to be WABA-approved would be silently force-routed through
            // Meta Cloud, ignoring the chosen engine. Empty provider == legacy /
            // workspace-default (unchanged for single-engine WABA workspaces).
            $campaignProvider = strtolower((string) ($campaign->provider ?? ''));
            $usedTemplateSender = false;
            if ($isTemplate && $tplCache
                && $tplCache->meta_template_id
                && strtoupper((string) $tplCache->meta_status) === 'APPROVED'
                && ($campaignProvider === '' || $campaignProvider === 'waba')
                && \App\Models\SystemSetting::get('waba_templates_v2_enabled', false)) {

                $usedTemplateSender = true;  // commit BEFORE try so exception still skips legacy

                $contactArr = [
                    'id'                => $contact->id,
                    'phone'             => $to,
                    'first_name'        => $contact->first_name,
                    'last_name'         => $contact->last_name,
                    'name'              => $contact->name,
                    'email'             => $contact->email,
                    'custom_attributes' => is_array($contact->custom_attributes) ? $contact->custom_attributes : [],
                ];

                try {
                    $bcCtl = app(\App\Http\Controllers\BroadcastsController::class);
                    $ref   = new \ReflectionClass($bcCtl);
                    $varsM = $ref->getMethod('varsForRecipient'); $varsM->setAccessible(true);
                    $wrapM = $ref->getMethod('wrapUrlsForRecipient'); $wrapM->setAccessible(true);

                    $vars = $varsM->invoke($bcCtl, $tplCache, $contactArr, (int) $wsId);
                    $vars = $wrapM->invoke($bcCtl, $vars, [
                        'workspace_id' => (int) $wsId,
                        'campaign_id'  => $campaign->id,
                        'contact_id'   => $contact->id,
                        'template_id'  => $tplCache->id,
                        'phone'        => $to,
                    ]);

                    // Image/Video/Document-header templates need a media URL per
                    // send. varsForRecipient sources it from the template's stored
                    // sample (attachment_file), but a template IMPORTED from Meta
                    // has none — Meta's sync gives us the header FORMAT, never the
                    // sample bytes. Without an image the send fails with Meta's
                    // "header: Format mismatch, expected IMAGE, received UNKNOWN".
                    // Fall back to the media the user uploaded on THIS campaign
                    // (custom_image/video/document), same source the legacy path uses.
                    $headerType = strtoupper((string) ($tplCache->attachment_type ?: ''));
                    if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
                        && empty($vars['header_media_url']) && empty($vars['header_media_id'])) {
                        $campMedia = $campaign->custom_image
                            ?: $campaign->custom_video
                            ?: $campaign->custom_document;
                        if (!empty($campMedia)) {
                            $vars['header_media_url'] = media_url($campMedia);
                            Log::info('[CAMPAIGN][WABA-TPL] header image from campaign upload', [
                                'campaign' => $campaign->id, 'header_type' => $headerType, 'url' => $vars['header_media_url'],
                            ]);
                        } else {
                            Log::warning('[CAMPAIGN][WABA-TPL] media-header template has NO image — send will fail Meta format check', [
                                'campaign' => $campaign->id, 'template' => $tplCache->template_name, 'header_type' => $headerType,
                            ]);
                        }
                    }

                    Log::info('[CAMPAIGN][WABA-TPL] sending template', [
                        'campaign'           => $campaign->id,
                        'contact'            => $contact->id,
                        'to'                 => $to,
                        'template'           => $tplCache->template_name,
                        'meta_template_id'   => $tplCache->meta_template_id,
                        'meta_category'      => $tplCache->meta_category,
                        'language'           => $tplCache->language,
                        'provider_config_id' => $tplCache->provider_config_id,
                        'vars'               => $vars,
                    ]);

                    $sender = new \App\Services\Waba\TemplateSender();
                    $res    = $sender->send($tplCache, $to, $vars);

                    Log::info('[CAMPAIGN][WABA-TPL] send result', [
                        'campaign' => $campaign->id,
                        'contact'  => $contact->id,
                        'to'       => $to,
                        'template' => $tplCache->template_name,
                        'ok'       => (bool) ($res['ok'] ?? false),
                        'wamid'    => $res['wamid'] ?? null,
                        'error'    => $res['error'] ?? null,
                    ]);

                    if ($res['ok']) {
                        $logRow?->update([
                            'status'              => 'sent',
                            'sent_at'             => now(),
                            'whatsapp_message_id' => $res['wamid'] ?? null,
                        ]);
                        $campaign->increment('sent_count');
                        // Warmer: count this number's send ONLY on confirmed success —
                        // no double-count on retry, no budget spent on a failed send.
                        if ($warmEnabled) { $warmer->recordSend($warmDevice); }
                    } else {
                        $this->recordSendFailure($logRow, $campaign, (string) ($res['error'] ?? 'unknown'), $maxAttempts, $retryBackoff);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[CAMPAIGN] TemplateSender threw — marking failed (NOT falling back to legacy)', [
                        'err' => $e->getMessage(),
                        'campaign' => $campaign->id,
                        'contact' => $contact->id,
                    ]);
                    $this->recordSendFailure($logRow, $campaign, 'TemplateSender exception: ' . $e->getMessage(), $maxAttempts, $retryBackoff);
                }
            }
            if ($usedTemplateSender) continue;

            Log::info('[CAMPAIGN] → dispatcher (sendRaw)', [
                'campaign_id' => $campaign->id,
                'contact_id'  => $contact->id,
                'to'          => $to,
                'from'        => $devicePhone,
                'body_len'    => strlen($body),
                'has_buttons' => isset($extras['buttons']) ? count($extras['buttons']) : 0,
                'has_footer'  => !empty($extras['footer']),
                'media_type'  => $mediaType,
                'media_path'  => $mediaPath,
            ]);
            try {
                // Send WITHOUT creating Conversation/Message rows.
                // Dispatcher builds a transient Message in memory only.
                $result = $this->dispatcher->sendRaw([
                    'from_number' => $devicePhone,
                    'to_number'   => $to,
                    'body'        => $body,
                    // Rich product-card: when the campaign carries an uploaded
                    // image/video/document, hand the path to the dispatcher so
                    // it routes to /api/send-media-message (media + this body as
                    // caption + meta buttons), not the plain-text endpoint.
                    'media_path'  => $mediaPath,
                    'media_type'  => $mediaType,
                    'meta'        => $extras ?: null,
                    // Multi-engine route: stamp the engine the operator chose
                    // for THIS campaign (Phase 3 wpcampaigns.provider) so the
                    // dispatcher routes Baileys/WABA/Twilio per-record instead
                    // of by the workspace-wide default. Empty/legacy campaigns
                    // (provider == workspace default) route exactly as before.
                    'provider'    => $campaign->provider,
                    // Carry the campaign's workspace so the dispatcher's
                    // forWorkspace($msg->workspace_id, $msg->user_id) device
                    // lookup resolves in the auth-less sweep context too.
                    'workspace_id' => $campaign->workspace_id,
                ], $userId, 'W');

                Log::info('[CAMPAIGN] dispatcher returned', [
                    'campaign_id' => $campaign->id,
                    'contact_id'  => $contact->id,
                    'ok'          => $result['ok'] ?? null,
                    'provider_id' => $result['provider_id'] ?? null,
                    'local_only'  => $result['local_only'] ?? null,
                    'error'       => $result['error'] ?? null,
                ]);
                if (($result['ok'] ?? false) === true) {
                    $logRow?->update([
                        'status'              => 'sent',
                        'sent_at'             => now(),
                        'whatsapp_message_id' => $result['provider_id'] ?? null,
                    ]);
                    $campaign->increment('sent_count');
                    // Warmer: count this number's send ONLY on confirmed success —
                    // no double-count on retry, no budget spent on a failed send.
                    if ($warmEnabled) { $warmer->recordSend($warmDevice); }
                } else {
                    $this->recordSendFailure($logRow, $campaign, (string) ($result['error'] ?? 'unknown'), $maxAttempts, $retryBackoff);
                }
            } catch (\Throwable $e) {
                Log::warning('campaign send threw', ['err' => $e->getMessage(), 'campaign' => $campaign->id, 'contact' => $contact->id]);
                $this->recordSendFailure($logRow, $campaign, $e->getMessage(), $maxAttempts, $retryBackoff);
            }
        }

        // Re-arm vs complete. When we stopped early for the daily cap or the
        // sending window AND recipients are still unsent, hand the campaign back
        // to the sweeper for the next slot so a large list is spread safely
        // across days / business hours. Recurring cadence is owned separately by
        // fireScheduledCampaign, so only NON-recurring campaigns re-arm here.
        $remaining = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['sent', 'delivered', 'read', 'responded'])
            ->count();

        // Retryable subset — non-delivered rows that still have attempts left.
        // Terminal failures (permanent, or retries exhausted) are stamped at
        // the cap so they're excluded; this is what lets the campaign converge.
        $retryable = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['sent', 'delivered', 'read', 'responded'])
            ->where('send_attempts', '<', $maxAttempts)
            ->count();

        if ($stopReason && $remaining > 0 && $campaign->schedule_type !== 'recurring') {
            if ($stopReason === 'time') {
                // Resume the rest after the pending gap so pacing is preserved
                // (re-arm to ~now + gap, in the campaign's timezone).
                try {
                    $rtz  = $campaign->timezone ?: config('app.timezone', 'UTC');
                    $next = \Illuminate\Support\Carbon::now($rtz)->addSeconds(max(1, (int) $resumeInSec));
                } catch (\Throwable $e) {
                    $next = \Illuminate\Support\Carbon::now('UTC')->addSeconds(max(1, (int) $resumeInSec));
                }
                $nextDate = $next->toDateString();
                $nextTime = $next->format('H:i:s');
            } else {
                [$nextDate, $nextTime] = $this->nextRunSlot($campaign, $stopReason);
            }
            $campaign->update([
                'status'        => 'scheduled',
                // The sweeper only fires schedule_type scheduled/recurring; a paced
                // "now" send keeps schedule_type='now', so flip it — otherwise the
                // remaining chunk would never be resumed.
                'schedule_type' => 'scheduled',
                'send_date'     => $nextDate,
                'send_time'     => $nextTime,
            ]);
            Log::info('[CAMPAIGN] paced run re-armed (Smart Delivery)', [
                'campaign_id' => $campaign->id,
                'reason'      => $stopReason,
                'sent_run'    => $sentThisRun,
                'remaining'   => $remaining,
                'next_date'   => $nextDate,
                'next_time'   => $nextTime,
            ]);
        } elseif (!$stopReason && $retryable > 0 && $campaign->schedule_type !== 'recurring') {
            // AUTO-RETRY re-arm. The run finished (no cap/window/time stop) but
            // some recipients failed transiently and still have attempts left.
            // Re-arm to the EARLIEST per-row backoff time so the sweeper resumes
            // and the loop retries only the rows whose next_attempt_at is due.
            // Converges: each pass either delivers a row or burns one attempt
            // until every recipient is sent or terminal (then it completes).
            $nextRetryAt = WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->whereNotIn('status', ['sent', 'delivered', 'read', 'responded'])
                ->where('send_attempts', '<', $maxAttempts)
                ->whereNotNull('next_attempt_at')
                ->min('next_attempt_at');
            $rtz = $campaign->timezone ?: config('app.timezone', 'UTC');
            try {
                $base = $nextRetryAt
                    ? \Illuminate\Support\Carbon::parse($nextRetryAt)->timezone($rtz)
                    : \Illuminate\Support\Carbon::now($rtz)->addSeconds($retryBackoff);
            } catch (\Throwable $e) {
                $base = \Illuminate\Support\Carbon::now('UTC')->addSeconds($retryBackoff);
            }
            $campaign->update([
                'status'        => 'scheduled',
                'schedule_type' => 'scheduled',
                'send_date'     => $base->toDateString(),
                'send_time'     => $base->format('H:i:s'),
            ]);
            Log::info('[CAMPAIGN] re-armed for auto-retry', [
                'campaign_id' => $campaign->id,
                'retryable'   => $retryable,
                'next'        => $base->toDateTimeString(),
            ]);
        } else {
            // Don't clobber a recurring campaign that fireScheduledCampaign
            // already re-armed to 'scheduled' for its next occurrence: that
            // re-arm runs synchronously BEFORE this async (afterResponse) loop,
            // so an unconditional 'completed' here would kill recurrence after a
            // single fire. Only complete when it wasn't re-armed — i.e. a one-off
            // scheduled/now run, or a recurring run past its repeat_until.
            $freshStatus = WpCampaign::where('id', $campaign->id)->value('status');
            if ($freshStatus !== 'scheduled') {
                $campaign->update(['status' => 'completed']);
            }
        }

        Log::info('[CAMPAIGN] dispatchCampaignNow done', [
            'campaign_id' => $campaign->id,
            'sent'        => $campaign->sent_count,
            'failed'      => $campaign->failed_count,
            'stop_reason' => $stopReason,
        ]);
    }

    /**
     * Is "now" inside the campaign's active sending window, in its own
     * timezone? Handles overnight windows (start > end, e.g. 22:00–06:00).
     * Times are zero-padded "HH:MM" so string comparison is correct.
     */
    private function withinSendWindow(string $tz, string $start, string $end): bool
    {
        try {
            $now = \Illuminate\Support\Carbon::now($tz)->format('H:i');
        } catch (\Throwable $e) {
            return true;   // bad tz — fail open, don't block the send
        }
        $s = substr($start, 0, 5);
        $e = substr($end, 0, 5);
        if ($s === $e) return true;                 // degenerate = no restriction
        return ($s < $e) ? ($now >= $s && $now <= $e)   // same-day window
                         : ($now >= $s || $now <= $e);  // overnight window
    }

    /**
     * Next [send_date, send_time] for a campaign that paused for the daily cap
     * or the closed sending window. Cap → tomorrow at the window open (or the
     * original send time). Window → today if it hasn't opened yet, else
     * tomorrow, at the window open time. All in the campaign's timezone.
     */
    private function nextRunSlot(WpCampaign $campaign, string $reason): array
    {
        $tz       = $campaign->timezone ?: config('app.timezone', 'UTC');
        $openTime = $campaign->window_start
            ? substr($campaign->window_start, 0, 5) . ':00'
            : ((string) ($campaign->send_time ?: '09:00:00'));

        try {
            $now = \Illuminate\Support\Carbon::now($tz);
        } catch (\Throwable $e) {
            $now = \Illuminate\Support\Carbon::now('UTC');
        }

        if ($reason === 'window' && $campaign->window_start) {
            // If today's window open is still ahead, resume today; else tomorrow.
            $todayOpen = \Illuminate\Support\Carbon::parse($now->toDateString() . ' ' . $openTime, $tz);
            $target    = $now->lt($todayOpen) ? $todayOpen : $todayOpen->copy()->addDay();
        } else {
            // Daily cap (or window with no explicit open) → next day.
            $target = \Illuminate\Support\Carbon::parse($now->toDateString() . ' ' . $openTime, $tz)->addDay();
        }

        return [$target->toDateString(), $target->format('H:i:s')];
    }

    /**
     * Flow campaigns: per-recipient POST to Node's existing flow-start
     * endpoint, mirroring DripEnrollmentService::launchFlow. The Node
     * runtime then owns delays, branching, and downstream sends. We
     * record the dispatch result on each WpCampaignContact row — `sent`
     * once Node ACKs the start, `failed` with the upstream error
     * otherwise. Wallet is charged 1 credit per recipient (refunded
     * on failure) to match text/template/button campaign accounting.
     */
    private function dispatchFlowCampaign(WpCampaign $campaign, $contacts, ?int $userId): void
    {
        $flowId  = (int) ($campaign->flow_id ?? 0);
        $flowIdB = (int) ($campaign->flow_id_b ?? 0);
        $abOn    = (bool) $campaign->ab_testing && $flowIdB > 0;
        if ($flowId <= 0) {
            $campaign->update(['status' => 'failed']);
            Log::warning('[CAMPAIGN-FLOW] no flow_id on campaign ' . $campaign->id);
            return;
        }

        // Active flow(s) owned by THIS campaign's workspace. Otherwise a
        // deleted/disabled flow (or a flow from another tenant slipped
        // in by id collision) would silently 404 in Node for every
        // recipient — fail the campaign fast instead. A/B campaigns load
        // both variants and route per-recipient by their assigned variant.
        $flow = \App\Models\Flow::query()
            ->where('id', $flowId)
            ->where('is_active', true)
            ->where('workspace_id', $campaign->workspace_id)
            ->first();
        if (!$flow) {
            Log::warning('[CAMPAIGN-FLOW] aborted — flow inactive or missing', [
                'campaign_id' => $campaign->id, 'flow_id' => $flowId, 'ws' => $campaign->workspace_id,
            ]);
            $campaign->update(['status' => 'failed']);
            \App\Models\WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'queued')
                ->update(['status' => 'failed', 'error_message' => 'Flow inactive or missing']);
            return;
        }
        $flowB = $abOn ? \App\Models\Flow::query()
            ->where('id', $flowIdB)
            ->where('is_active', true)
            ->where('workspace_id', $campaign->workspace_id)
            ->first() : null;
        if ($abOn && !$flowB) {
            // Variant B missing/inactive — fail the B half so the run doesn't
            // silently fall back to A for every B recipient.
            Log::warning('[CAMPAIGN-FLOW] variant B flow inactive or missing', [
                'campaign_id' => $campaign->id, 'flow_id_b' => $flowIdB, 'ws' => $campaign->workspace_id,
            ]);
        }

        // Sender device — Node addresses flow sessions by device phone.
        $device = $campaign->device_id
            ? \App\Models\Device::query()->find($campaign->device_id)
            : null;
        $devicePhone = $device
            ? preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number))
            : null;
        if (!$devicePhone) {
            Log::warning('[CAMPAIGN-FLOW] aborted — no paired device phone', [
                'campaign_id' => $campaign->id, 'device_id' => $campaign->device_id,
            ]);
            $campaign->update(['status' => 'failed']);
            \App\Models\WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'queued')
                ->update(['status' => 'failed', 'error_message' => 'No paired device on campaign']);
            return;
        }

        $nodeUrl = (string) (\App\Models\SystemSetting::get('baileys_server_url', '') ?: env('SERVER_URL', ''));
        if ($nodeUrl === '') {
            Log::warning('[CAMPAIGN-FLOW] aborted — NODE bridge URL not configured', [
                'campaign_id' => $campaign->id,
            ]);
            $campaign->update(['status' => 'failed']);
            \App\Models\WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', 'queued')
                ->update(['status' => 'failed', 'error_message' => 'NODE bridge URL not configured']);
            return;
        }
        $nodeUrl = rtrim($nodeUrl, '/');
        $token   = node_token();

        Log::info('[CAMPAIGN-FLOW] launching', [
            'campaign_id' => $campaign->id,
            'flow_id'     => $flow->id,
            'recipients'  => $contacts->count(),
            'device'      => $devicePhone,
        ]);

        foreach ($contacts as $contact) {
            $logRow = \App\Models\WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->where('contact_id', $contact->id)
                ->first();

            $to = preg_replace('/\D+/', '', (string) (($contact->country_code ?? '') . $contact->mobile));
            if ($to === '') {
                $logRow?->update(['status' => 'failed', 'error_message' => 'No mobile number on contact']);
                $campaign->increment('failed_count');
                continue;
            }
            // Plan-first billing (OverflowBilling) — free under the plan's
            // monthly_messages_limit, wallet credit only on overflow. Same as
            // the text/template path; no wallet pre-gate.
            try {
                $wsObj = \App\Models\Workspace::find($campaign->workspace_id);
                if ($wsObj) {
                    $usedThisMonth = WpCampaignContact::query()
                        ->whereIn('campaign_id', WpCampaign::where('workspace_id', $campaign->workspace_id)->pluck('id'))
                        ->whereIn('status', ['sent', 'delivered', 'read', 'responded'])
                        ->where('updated_at', '>=', now()->startOfMonth())
                        ->count();
                    // Flow-campaign send → recipient's country MARKETING rate
                    // (no-ops to flat when per-country pricing is OFF).
                    \App\Services\OverflowBilling::consumeOne($wsObj, $usedThisMonth, optional($contact)->mobile, 'marketing');
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                $logRow?->update(['status' => 'failed', 'error_message' => 'Plan cap reached — top up wallet to keep sending']);
                $campaign->increment('failed_count');
                continue;
            }

            // A/B variant routing — recipients assigned 'B' run flow_id_b when
            // the campaign is in A/B mode. If B is missing/inactive at this
            // point, fail the recipient instead of silently sending the A flow.
            $isVariantB = $abOn && ($logRow?->variant === 'B');
            if ($isVariantB && !$flowB) {
                $logRow?->update(['status' => 'failed', 'error_message' => 'Variant B flow inactive or missing']);
                $campaign->increment('failed_count');
                continue;
            }
            $chosenFlow = $isVariantB ? $flowB : $flow;

            try {
                $r = \Illuminate\Support\Facades\Http::withHeaders([
                        'X-Node-Token' => $token,
                    ])
                    ->timeout(15)
                    ->acceptJson()
                    ->post($nodeUrl . '/api/flow/start/' . rawurlencode($devicePhone), [
                        'flowId'            => $chosenFlow->id,
                        'targetPhoneNumber' => $to,
                        // Diagnostic crumbs — Node logs them so ops can
                        // correlate flow sessions to campaigns.
                        'campaignId'        => $campaign->id,
                        'contactId'         => $contact->id,
                        'variant'           => $isVariantB ? 'B' : 'A',
                    ]);

                if ($r->successful()) {
                    $logRow?->update(['status' => 'sent', 'sent_at' => now()]);
                    $campaign->increment('sent_count');
                } else {
                    $err = 'Node ' . $r->status() . ': ' . mb_substr((string) $r->body(), 0, 150);
                    $logRow?->update(['status' => 'failed', 'error_message' => $err]);
                    $campaign->increment('failed_count');
                }
            } catch (\Throwable $e) {
                $err = 'Node unreachable: ' . mb_substr($e->getMessage(), 0, 150);
                $logRow?->update(['status' => 'failed', 'error_message' => $err]);
                $campaign->increment('failed_count');
            }
        }

        $campaign->update(['status' => 'completed']);
        Log::info('[CAMPAIGN-FLOW] done', [
            'campaign_id' => $campaign->id,
            'sent'        => $campaign->sent_count,
            'failed'      => $campaign->failed_count,
        ]);
    }

    // -----------------------------------------------------------------
    // Show / update
    // -----------------------------------------------------------------

    public function show($id, Request $request = null)
    {
        $request = $request ?? request();
        $campaign = WpCampaign::query()
            ->forCurrentWorkspace()
            ->with('contacts')
            ->findOrFail($id);

        // Self-heal the aggregate counters from the per-recipient log before we
        // render anything — Meta delivery/read webhooks patch the log rows but
        // historically never these columns, so the KPI cards (which read the
        // columns) were stuck at 0 while the funnel (which reads the log) was
        // right. This makes both consistent, and backfills campaigns whose
        // webhooks fired before the counter-sync fix landed.
        $campaign->recomputeAggregates();

        // Live-refresh JSON branch — user-wa-campaigns-detail.js
        // polls every 15 s with `?partial=1` to repaint the KPI tiles
        // + status pill without a full page reload. Shape mirrors
        // BroadcastsController::statistics so the frontend keeps a
        // single update path.
        if ($request->wantsJson() || $request->boolean('partial')) {
            $totalRecipients = (int) ($campaign->total_recipients ?: $campaign->contacts->count());
            $pct = function (int $n, int $base): float {
                return $base > 0 ? round($n / $base * 100, 1) : 0.0;
            };
            return response()->json([
                'ok' => true,
                'status' => (string) ($campaign->status ?? 'draft'),
                'stats'  => [
                    'recipients'    => $totalRecipients,
                    'sent'          => (int) $campaign->sent_count,
                    'delivered'     => (int) $campaign->delivered_count,
                    'read'          => (int) $campaign->read_count,
                    'replies'       => (int) $campaign->responded_count,
                    'clicks'        => (int) $campaign->clicked_count,
                    'failed'        => (int) $campaign->failed_count,
                    'delivered_pct' => $pct((int) $campaign->delivered_count, $totalRecipients),
                    'read_pct'      => $pct((int) $campaign->read_count, (int) $campaign->delivered_count),
                    'replies_pct'   => $pct((int) $campaign->responded_count, $totalRecipients),
                    'clicks_pct'    => $pct((int) $campaign->clicked_count, $totalRecipients),
                    'failed_pct'    => $pct((int) $campaign->failed_count, $totalRecipients),
                ],
            ]);
        }

        // ---------------------------------------------------------
        // Recipient log slices used by the Messages + Engagement tabs.
        // ---------------------------------------------------------
        $allContacts = $campaign->contacts; // already eager-loaded
        // Message-log search — when a query is present we pull a wider slice
        // and filter in PHP (phone/name are encrypted, so no SQL LIKE), so
        // the search works fully server-side without any JS/build upload.
        $msgSearch = trim((string) request('q', ''));
        $messages = WpCampaignContact::where('campaign_id', $campaign->id)
            ->latest('id')
            ->take($msgSearch !== '' ? 500 : 20)
            ->get();
        $replies = WpCampaignContact::where('campaign_id', $campaign->id)
            ->whereNotNull('responded_at')
            ->latest('responded_at')
            ->take(10)
            ->get();

        // ---------------------------------------------------------
        // Header right-side metric tiles (ROI / Audience / Cost / CPC / Quality).
        // No real cost-tracking or revenue-attribution yet — we derive each
        // from recipient counters and the campaign's contact group makeup.
        // TODO: replace with real cost-tracking + revenue tables when available.
        // ---------------------------------------------------------
        $sent      = (int) $campaign->sent_count;
        $delivered = (int) $campaign->delivered_count;
        $responded = (int) $campaign->responded_count;
        $clicked   = (int) $campaign->clicked_count;

        // Audience: pick the largest contact group across this campaign's
        // recipients. WpCampaign has no `groups` column, so we walk the
        // recipient log -> Contact -> contact_group (encrypted JSON array)
        // and tally group ids. The biggest bucket wins.
        $audienceLabel = 'All contacts';
        $contactIds = $allContacts->pluck('contact_id')->filter()->unique()->values();
        if ($contactIds->isNotEmpty()) {
            $contactRows = Contact::query()->forCurrentWorkspace()->whereIn('id', $contactIds)->get(['id', 'contact_group']);
            $groupTally = [];
            foreach ($contactRows as $c) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                foreach ($list as $gid) {
                    $key = (string) $gid;
                    $groupTally[$key] = ($groupTally[$key] ?? 0) + 1;
                }
            }
            if (!empty($groupTally)) {
                arsort($groupTally);
                $topGroupId = (int) array_key_first($groupTally);
                // Workspace-scope the label lookup so cross-tenant
                // ids can't leak a group name into the audience label.
                $topGroup = ContactGroup::query()
                    ->where('workspace_id', $campaign->workspace_id)
                    ->find($topGroupId);
                if ($topGroup && $topGroup->user_group) {
                    $audienceLabel = (string) $topGroup->user_group;
                }
            }
        }

        // TODO: replace with real cost-tracking when message-pricing infra lands.
        $costPerMsg = 0.04;
        $costValue  = $sent * $costPerMsg;
        $cpcValue   = $clicked > 0 ? ($costValue / $clicked) : 0;

        $header = [
            // ROI: response-rate scaled to a 0-10 score (responded / sent * 10).
            'roi'      => $sent > 0 ? round($responded / $sent * 10, 1) : 0,
            'audience' => $audienceLabel,
            'cost'     => '$' . number_format($costValue, 2),
            'cpc'      => '$' . number_format($cpcValue, 2),
            // Quality: delivery-rate scaled to a 0-10 score.
            'quality'  => $sent > 0 ? round($delivered / $sent * 10, 1) : 0,
        ];

        // ---------------------------------------------------------
        // Chart data — built from the recipient log when populated, or
        // sensible fallbacks derived from the campaign counters when the
        // log is empty (e.g. brand-new campaigns where the SendWaCampaign
        // job hasn't run yet).
        // ---------------------------------------------------------
        $chartData = $this->buildChartData($campaign, $allContacts);

        // Timeline placeholder — once the SendWaCampaign job lands it can
        // append real events into a campaign_events table. For now mirror the
        // old controller's "fetch + map" shape with mock data so the Blade
        // panel renders.
        // Render every timeline time in the campaign's OWN timezone (the zone
        // it was scheduled/sent in) so operators don't see raw UTC. Stored
        // timestamps are UTC; wa_local() converts, pinned to $campaign->timezone.
        $campTz    = $campaign->timezone ?: null;
        $createdAt = wa_local($campaign->created_at, $campTz);
        $updatedAt = wa_local($campaign->updated_at, $campTz);
        $timeline = [
            [
                'icon'   => '1',
                'title'  => 'Campaign queued',
                'detail' => $campaign->total_recipients . ' recipients loaded.',
                'time'   => $createdAt?->format('H:i') ?? '--:--',
            ],
            [
                'icon'   => '2',
                'title'  => 'Status: ' . ucfirst((string) $campaign->status),
                'detail' => 'Schedule type ' . ($campaign->schedule_type ?: 'now') . '.',
                'time'   => $updatedAt?->format('H:i') ?? '--:--',
            ],
            [
                'icon'   => '3',
                'title'  => 'Delivery progress',
                'detail' => $campaign->delivered_count . ' of ' . $campaign->total_recipients . ' delivered.',
                'time'   => $updatedAt?->format('H:i') ?? '--:--',
            ],
            [
                'icon'   => '4',
                'title'  => 'Reads recorded',
                'detail' => $campaign->read_count . ' read receipts captured.',
                'time'   => $updatedAt?->format('H:i') ?? '--:--',
            ],
            [
                'icon'   => '5',
                'title'  => 'Failures observed',
                'detail' => $campaign->failed_count . ' messages failed.',
                'time'   => $updatedAt?->format('H:i') ?? '--:--',
            ],
        ];

        // ---------------------------------------------------------
        // Conversion funnel: Recipients -> Delivered -> Read -> Clicked -> Replied.
        // All five values come from the WpCampaignContact log (real per-recipient
        // status), so the funnel reflects what actually happened — not the
        // synthesised demo numbers the static blade had.
        // ---------------------------------------------------------
        $logBase = WpCampaignContact::where('campaign_id', $campaign->id);
        $totalLog     = (clone $logBase)->count();
        $deliveredLog = (clone $logBase)->whereIn('status', ['delivered','read','sent'])->count();
        $readLog      = (clone $logBase)->whereNotNull('read_at')->count() + (clone $logBase)->where('status', 'read')->count();
        $clickedLog   = (clone $logBase)->where('clicked', true)->count();
        $repliedLog   = (clone $logBase)->whereNotNull('responded_at')->count();
        // Use whichever is bigger between the campaign counters and the log
        // counts so newly-fired sends don't appear empty before the log
        // catches up (the dispatcher writes to log+counter).
        $totalRecipients = max($totalLog, (int) $campaign->total_recipients);
        $pct = fn ($n) => $totalRecipients > 0 ? round(($n / $totalRecipients) * 100, 1) : 0.0;
        $funnel = [
            'recipients'    => $totalRecipients,
            'delivered'     => max($deliveredLog, (int) $campaign->delivered_count),
            'read'          => max($readLog,      (int) $campaign->read_count),
            'clicked'       => max($clickedLog,   (int) $campaign->clicked_count),
            'replied'       => max($repliedLog,   (int) $campaign->responded_count),
        ];
        $funnel['delivered_pct'] = $pct($funnel['delivered']);
        $funnel['read_pct']      = $pct($funnel['read']);
        $funnel['clicked_pct']   = $pct($funnel['clicked']);
        $funnel['replied_pct']   = $pct($funnel['replied']);

        // ---------------------------------------------------------
        // Read heatmap — 7 days × 24 hours grid of read counts. Built
        // from `read_at` timestamps on the recipient log.
        // ---------------------------------------------------------
        $heatmap = array_fill(0, 7, array_fill(0, 24, 0));
        $readRows = (clone $logBase)->whereNotNull('read_at')->get(['read_at']);
        foreach ($readRows as $r) {
            if (!$r->read_at) continue;
            $dow = (int) $r->read_at->dayOfWeek; // 0=Sun..6=Sat
            $hr  = (int) $r->read_at->hour;
            $heatmap[$dow][$hr]++;
        }

        // ---------------------------------------------------------
        // Top performers — group recipient log by contact_group, then
        // rank by read-rate. Empty when the campaign has no group-based
        // segmentation.
        // ---------------------------------------------------------
        $segments = [];
        if (!empty($contactRows ?? null)) {
            $byGroup = []; // gid => ['recipients', 'replies', 'reads', 'opt_outs']
            foreach ($contactRows as $c) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                foreach ($list as $gid) {
                    $key = (string) $gid;
                    if (!isset($byGroup[$key])) $byGroup[$key] = ['recipients'=>0,'replies'=>0,'reads'=>0,'opt_outs'=>0];
                    $byGroup[$key]['recipients']++;
                    $row = $allContacts->firstWhere('contact_id', $c->id);
                    if ($row) {
                        if ($row->responded_at) $byGroup[$key]['replies']++;
                        if ($row->read_at)      $byGroup[$key]['reads']++;
                        if ($row->is_unsubscribed) $byGroup[$key]['opt_outs']++;
                    }
                }
            }
            foreach ($byGroup as $gid => $stats) {
                $g = ContactGroup::find((int) $gid);
                if (!$g) continue;
                $segments[] = [
                    'name'       => (string) ($g->user_group ?: 'Group #' . $gid),
                    'recipients' => $stats['recipients'],
                    'replies'    => $stats['replies'],
                    'opt_outs'   => $stats['opt_outs'],
                    'read_pct'   => $stats['recipients'] > 0 ? round($stats['reads'] / $stats['recipients'] * 100, 1) : 0.0,
                ];
            }
            usort($segments, fn ($a, $b) => $b['read_pct'] <=> $a['read_pct']);
            $segments = array_slice($segments, 0, 5);
        }

        // ---------------------------------------------------------
        // Per-tab data sources — every panel was previously hardcoded.
        // ---------------------------------------------------------

        // Messages tab — "Sent content" preview reads the real body /
        // buttons / footer / header used for this campaign. For template
        // sends we pull from the template; for custom sends from the
        // campaign's own columns.
        $tpl = $campaign->template_id ? \App\Models\WaTemplate::find($campaign->template_id) : null;
        $isTemplateCampaign = $campaign->campaign_type === 'template' && $tpl;
        $previewBody     = $isTemplateCampaign ? (string) $tpl->template_body : (string) $campaign->custom_message;
        $previewFooter   = $isTemplateCampaign ? (string) ($tpl->footer ?? '') : (string) ($campaign->custom_footer ?? '');
        $previewHeader   = $isTemplateCampaign ? (string) ($tpl->header ?? '') : (string) ($campaign->custom_header ?? '');
        $previewButtons  = $isTemplateCampaign
            ? (is_array($tpl->buttons) ? $tpl->buttons : [])
            : (is_array($campaign->custom_buttons) ? $campaign->custom_buttons : []);
        $previewTemplateName = $isTemplateCampaign ? (string) $tpl->template_name : ('Custom · ' . $campaign->campaign_name);
        $previewCategory = $isTemplateCampaign
            ? ucfirst((string) ($tpl->category ?? 'marketing'))
            : ucfirst((string) ($campaign->campaign_type ?: 'custom'));

        // Engagement tab — 4 top metric cards. Re-derive percentages
        // from the campaign counters so the displayed values agree with
        // the funnel card on the overview tab.
        $sentN        = (int) $campaign->sent_count;
        $readN        = (int) max($campaign->read_count, $readLog);
        $clickedN     = (int) max($campaign->clicked_count, $clickedLog);
        $repliedN     = (int) max($campaign->responded_count, $repliedLog);
        $optOutsN     = (int) $allContacts->where('is_unsubscribed', true)->count();
        $totalForPct  = max($sentN, 1);
        $engagement   = [
            'opened_pct'  => round($readN    / $totalForPct * 100, 1),
            'opened_n'    => $readN,
            'clicked_pct' => round($clickedN / $totalForPct * 100, 1),
            'clicked_n'   => $clickedN,
            'replied_pct' => round($repliedN / $totalForPct * 100, 1),
            'replied_n'   => $repliedN,
            'optout_pct'  => $totalRecipients > 0 ? round($optOutsN / $totalRecipients * 100, 1) : 0.0,
            'optout_n'    => $optOutsN,
        ];

        // Engagement tab — "Top buttons" card. We don't track per-button
        // clicks yet (would need a click-tracking column per button id);
        // for now surface the campaign's buttons with the campaign's
        // total click count split evenly. Better than fake "Shop now 1,118".
        $btnSrc = $isTemplateCampaign
            ? (is_array($tpl->buttons) ? $tpl->buttons : [])
            : (is_array($campaign->custom_buttons) ? $campaign->custom_buttons : []);
        $btnRows = [];
        $btnCount = count($btnSrc);
        $perBtn   = $btnCount > 0 ? (int) floor($clickedN / $btnCount) : 0;
        foreach ($btnSrc as $idx => $b) {
            if (!is_array($b)) continue;
            $btnRows[] = [
                'label' => (string) ($b['text'] ?? ('Button ' . ($idx + 1))),
                'count' => $perBtn,
                'pct'   => $clickedN > 0 ? round(($perBtn / $clickedN) * 100) : 0,
            ];
        }

        // Recipients tab — segment totals card (left side). Top 3 by
        // recipient count from $segments we already computed. Empty
        // when no contact-group data exists.
        $segmentTotals = collect($segments)
            ->sortByDesc('recipients')
            ->take(3)
            ->map(fn ($s) => ['name' => $s['name'], 'recipients' => $s['recipients'], 'read_pct' => $s['read_pct']])
            ->values()
            ->all();

        // Recipients tab — recipient table (the per-row analytics).
        // Pull straight from the WpCampaignContact log; show all rows
        // up to 200, ordered by most-recently-sent.
        $recipientRows = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Backfill recipient_name + phone_number from the linked Contact for
        // any row the send path didn't stamp them on (older campaign_contact
        // rows have null phone → the delivery table showed "—" and
        // "Recipient #<id>"). Contact fields decrypt via Eloquent, so no
        // ciphertext ever reaches the view. One query covers all three sets.
        $this->hydrateRecipientRows([$messages, $replies, $recipientRows]);

        // Apply the message-log search now that name + phone are resolved.
        if ($msgSearch !== '') {
            $needle   = mb_strtolower($msgSearch);
            $messages = $messages->filter(function ($m) use ($needle) {
                return str_contains(mb_strtolower((string) $m->recipient_name . ' ' . (string) $m->phone_number), $needle);
            })->take(50)->values();
        }

        // Recipients tab — Audience cleanup card. Compute the real
        // uploaded → final-send-list breakdown.
        $audienceStats = [
            'uploaded'     => (int) $campaign->total_recipients,
            'duplicates'   => 0, // would need pre-dedupe counter; not tracked
            'invalid'      => $allContacts->whereNull('phone_number')->count(),
            'opt_out_skip' => $optOutsN,
            'final_list'   => max(0, (int) $campaign->total_recipients - $optOutsN),
        ];

        // Failures tab — header count + recent error table.
        $failureRows = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        $failedTotal = $failureRows->count(); // capped at 50 for the visible table; the campaign counter has the real total
        $failedCount = (int) $campaign->failed_count;

        return view('user.wa-campaigns.detail', compact(
            'campaign', 'timeline', 'messages', 'replies', 'header', 'chartData',
            'funnel', 'heatmap', 'segments',
            'previewBody', 'previewFooter', 'previewHeader', 'previewButtons',
            'previewTemplateName', 'previewCategory',
            'engagement', 'btnRows',
            'segmentTotals', 'recipientRows', 'audienceStats',
            'failureRows', 'failedCount',
        ));
    }

    /**
     * Fill in recipient_name + phone_number on campaign-contact rows from the
     * linked Contact whenever the row itself doesn't carry them. Both columns
     * are encrypted casts, so setting them in-memory round-trips cleanly and
     * the Blade / mask_phone() sees a real value instead of "—". Accepts any
     * number of collections and hydrates them from a single Contact query.
     */
    private function hydrateRecipientRows(array $collections): void
    {
        $ids = collect();
        foreach ($collections as $col) {
            if ($col) $ids = $ids->concat($col->pluck('contact_id'));
        }
        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) return;

        // SECURITY: scope to the current workspace so recipient rows carrying
        // a foreign contact id (legacy pre-scoping data) never decrypt another
        // tenant's name + phone into the campaign detail view.
        $contacts = Contact::query()->forCurrentWorkspace()->whereIn('id', $ids)
            ->get(['id', 'name', 'first_name', 'last_name', 'country_code', 'mobile'])
            ->keyBy('id');

        foreach ($collections as $col) {
            if (!$col) continue;
            $col->transform(function ($row) use ($contacts) {
                $c = $row->contact_id ? $contacts->get($row->contact_id) : null;
                if (!$c) return $row;
                if (empty($row->recipient_name)) {
                    $nm = trim((string) ($c->name ?? '')) ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''));
                    if ($nm !== '') $row->recipient_name = $nm;
                }
                if (empty($row->phone_number)) {
                    $cc  = preg_replace('/\D+/', '', (string) ($c->country_code ?? ''));
                    $mob = preg_replace('/\D+/', '', (string) ($c->mobile ?? ''));
                    if ($mob !== '') {
                        $row->phone_number = ($cc !== '' && strpos($mob, $cc) !== 0) ? ($cc . $mob) : $mob;
                    }
                }
                return $row;
            });
        }
    }

    /**
     * CSV export of every recipient delivery row for a campaign — full phone +
     * name (resolved from the Contact), status and the delivery/engagement
     * timeline. Streams so a large audience never buffers into memory.
     */
    public function exportRecipients($id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        $rows = WpCampaignContact::where('campaign_id', $campaign->id)
            ->orderByDesc('id')->get();
        // SECURITY: scope contact hydration to the current workspace so a
        // campaign that carries a foreign contact id (legacy rows created
        // before store() was scoped) can never decrypt/export another tenant's
        // name + phone. Same-workspace recipients hydrate unchanged; foreign
        // ids fall through to the stored recipient_name/phone_number or the
        // "Contact #id" placeholder.
        $contacts = Contact::query()->forCurrentWorkspace()
            ->whereIn('id', $rows->pluck('contact_id')->filter()->unique())
            ->get(['id', 'name', 'first_name', 'last_name', 'country_code', 'mobile'])
            ->keyBy('id');

        $tz  = $campaign->timezone ?: wa_tz();
        $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->setTimezone($tz)->format('Y-m-d H:i') : '';
        // Neutralise CSV formula injection (=, +, -, @ leading a cell).
        $safe = fn ($v) => (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true)) ? "'" . $v : (string) $v;

        $filename = 'campaign-' . $campaign->id . '-recipients-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $contacts, $fmt, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Recipient', 'Phone', 'Variant', 'Status', 'Clicked',
                'Queued', 'Sent', 'Delivered', 'Read', 'Responded', 'Last event', 'Error',
            ]);
            foreach ($rows as $r) {
                $c = $r->contact_id ? $contacts->get($r->contact_id) : null;
                $name = $r->recipient_name
                    ?: ($c ? (trim((string) ($c->name ?? '')) ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))) : '')
                    ?: ('Contact #' . $r->contact_id);
                $phone = (string) $r->phone_number;
                if ($phone === '' && $c) {
                    $cc  = preg_replace('/\D+/', '', (string) ($c->country_code ?? ''));
                    $mob = preg_replace('/\D+/', '', (string) ($c->mobile ?? ''));
                    $phone = $mob === '' ? '' : (($cc !== '' && strpos($mob, $cc) !== 0) ? $cc . $mob : $mob);
                }
                $lastEvent = $r->responded_at ? 'Reply'
                    : ($r->clicked_at ? 'Button tap'
                    : ($r->read_at ? 'Read'
                    : ($r->delivered_at ? 'Delivered'
                    : ($r->sent_at ? 'Sent' : 'Queued'))));
                fputcsv($out, [
                    $safe($name),
                    $safe($phone),
                    $safe((string) ($r->variant ?? '')),
                    ucfirst((string) ($r->status ?: 'queued')),
                    $r->clicked ? 'Yes' : 'No',
                    $fmt($r->created_at),
                    $fmt($r->sent_at),
                    $fmt($r->delivered_at),
                    $fmt($r->read_at),
                    $fmt($r->responded_at),
                    $lastEvent,
                    $safe((string) ($r->error_message ?? '')),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Build the JSON-serializable chart payload that the Blade injects into
     * `window.WA_CAMPAIGN_DATA`. Each key matches a `#chart-*` container in
     * the detail view. Where recipient-log data is sparse (e.g. brand new
     * campaigns) we fall back to deterministic shapes derived from the
     * counters on `wpcampaigns` so the charts always render something
     * meaningful instead of blank canvases.
     */
    protected function buildChartData(WpCampaign $campaign, $contacts): array
    {
        // All day/hour bucketing below must happen in the campaign's OWN
        // timezone, not raw UTC — otherwise India's "messages per hour" and
        // read-heatmap charts sit 5h30 off and rows land in the wrong day.
        // Stored timestamps are UTC; $loc() converts each to the campaign tz
        // before we read its day-of-week / hour / calendar day.
        $tz  = $campaign->timezone ?: wa_tz();
        $loc = fn ($t) => $t ? $t->copy()->setTimezone($tz) : null;

        // ----- chart-delivery: 7-day timeline of sent / delivered / read -----
        $now = Carbon::now($tz);
        $deliveryCategories = [];
        $sentSeries = $deliveredSeries = $readSeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $deliveryCategories[] = $day->format('M j');
            $sentSeries[]      = $contacts->whereNotNull('sent_at')
                ->filter(fn ($c) => $c->sent_at && $loc($c->sent_at)->isSameDay($day))->count();
            $deliveredSeries[] = $contacts->whereNotNull('delivered_at')
                ->filter(fn ($c) => $c->delivered_at && $loc($c->delivered_at)->isSameDay($day))->count();
            $readSeries[]      = $contacts->whereNotNull('read_at')
                ->filter(fn ($c) => $c->read_at && $loc($c->read_at)->isSameDay($day))->count();
        }
        $logHasTimeline = array_sum($sentSeries) > 0 || array_sum($deliveredSeries) > 0;
        if (!$logHasTimeline) {
            // Fallback: distribute the campaign's counters evenly across 7 buckets.
            $bucketSent = (int) floor(((int) $campaign->sent_count) / 7);
            $bucketDel  = (int) floor(((int) $campaign->delivered_count) / 7);
            $bucketRead = (int) floor(((int) $campaign->read_count) / 7);
            $sentSeries      = array_fill(0, 7, $bucketSent);
            $deliveredSeries = array_fill(0, 7, $bucketDel);
            $readSeries      = array_fill(0, 7, $bucketRead);
        }

        // ----- chart-status: pie of sent / delivered / read / failed / responded -----
        // Derive from the recipient log; fall back to campaign counters.
        $statusFromLog = [
            'sent'      => $contacts->where('status', 'sent')->count(),
            'delivered' => $contacts->where('status', 'delivered')->count(),
            'read'      => $contacts->where('status', 'read')->count(),
            'failed'    => $contacts->where('status', 'failed')->count(),
            'responded' => $contacts->whereNotNull('responded_at')->count(),
        ];
        if (array_sum($statusFromLog) === 0) {
            $statusFromLog = [
                'sent'      => (int) $campaign->sent_count,
                'delivered' => (int) $campaign->delivered_count,
                'read'      => (int) $campaign->read_count,
                'failed'    => (int) $campaign->failed_count,
                'responded' => (int) $campaign->responded_count,
            ];
        }

        // ----- chart-throughput: messages per hour (24 buckets) -----
        $throughputCats = [];
        $throughputData = [];
        for ($h = 0; $h < 24; $h++) {
            $throughputCats[] = sprintf('%02d:00', $h);
            $throughputData[] = $contacts->whereNotNull('sent_at')
                ->filter(fn ($c) => $c->sent_at && (int) $loc($c->sent_at)->format('G') === $h)
                ->count();
        }
        if (array_sum($throughputData) === 0) {
            $sentTotal = (int) $campaign->sent_count;
            $perBucket = (int) floor($sentTotal / 24);
            $throughputData = array_fill(0, 24, $perBucket);
        }

        // ----- chart-engagement: clicks + replies over the past 7 days -----
        $engagementCats = $deliveryCategories;
        $clicksSeries   = [];
        $repliesSeries  = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $clicksSeries[]  = $contacts->whereNotNull('clicked_at')
                ->filter(fn ($c) => $c->clicked_at && $loc($c->clicked_at)->isSameDay($day))->count();
            $repliesSeries[] = $contacts->whereNotNull('responded_at')
                ->filter(fn ($c) => $c->responded_at && $loc($c->responded_at)->isSameDay($day))->count();
        }
        if (array_sum($clicksSeries) === 0 && array_sum($repliesSeries) === 0) {
            $clicksSeries  = array_fill(0, 7, 0);
            $repliesSeries = array_fill(0, 7, 0);
        }

        // ----- chart-read-heatmap: 24-hour read distribution by weekday -----
        $heatmapDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $heatmapHours = ['00','03','06','09','12','15','18','21'];
        $heatmap = [];
        foreach ($heatmapDays as $idx => $dayLabel) {
            $row = ['name' => $dayLabel, 'data' => []];
            foreach ($heatmapHours as $hourLabel) {
                $hourInt = (int) $hourLabel;
                $value = $contacts->whereNotNull('read_at')
                    ->filter(function ($c) use ($idx, $hourInt, $loc) {
                        if (!$c->read_at) return false;
                        $r   = $loc($c->read_at);
                        $dow = (int) $r->format('N') - 1; // Mon=0
                        $h   = (int) $r->format('G');
                        return $dow === $idx && $h >= $hourInt && $h < ($hourInt + 3);
                    })->count();
                $row['data'][] = ['x' => $hourLabel, 'y' => $value];
            }
            $heatmap[] = $row;
        }

        // ----- chart-intents: TODO — no intent labels tracked yet. -----
        // Fallback: split the responded_count across three generic buckets.
        $intentTotal = (int) $campaign->responded_count;
        $intents = [
            'labels' => ['Order', 'Support', 'Other'],
            'series' => [
                (int) round($intentTotal * 0.4),
                (int) round($intentTotal * 0.3),
                (int) round($intentTotal * 0.3),
            ],
        ];

        // ----- chart-segments: top 5 group breakdown of recipients -----
        $contactIds = $contacts->pluck('contact_id')->filter()->unique()->values();
        $segmentLabels = [];
        $segmentValues = [];
        if ($contactIds->isNotEmpty()) {
            $contactRows = Contact::query()->forCurrentWorkspace()->whereIn('id', $contactIds)->get(['id', 'contact_group']);
            $tally = [];
            foreach ($contactRows as $c) {
                $list = is_array($c->contact_group) ? $c->contact_group : [];
                foreach ($list as $gid) {
                    $key = (string) $gid;
                    $tally[$key] = ($tally[$key] ?? 0) + 1;
                }
            }
            arsort($tally);
            $top = array_slice($tally, 0, 5, true);
            if (!empty($top)) {
                $groupRows = ContactGroup::whereIn('id', array_keys($top))->get(['id', 'user_group']);
                foreach ($top as $gid => $count) {
                    $row = $groupRows->firstWhere('id', (int) $gid);
                    $segmentLabels[] = $row && $row->user_group ? (string) $row->user_group : ('Group #' . $gid);
                    $segmentValues[] = $count;
                }
            }
        }
        if (empty($segmentLabels)) {
            $segmentLabels = ['All contacts'];
            $segmentValues = [(int) $campaign->total_recipients];
        }

        // ----- chart-failures: top 5 failure reasons (from decrypted error_message). -----
        $failureLabels = [];
        $failureValues = [];
        $failedRows = $contacts->where('status', 'failed');
        if ($failedRows->count() > 0) {
            $reasons = [];
            foreach ($failedRows as $row) {
                $reason = trim((string) ($row->error_message ?: 'Unknown'));
                if ($reason === '') $reason = 'Unknown';
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }
            arsort($reasons);
            $top = array_slice($reasons, 0, 5, true);
            foreach ($top as $label => $count) {
                $failureLabels[] = $label;
                $failureValues[] = $count;
            }
        }
        if (empty($failureLabels)) {
            // Fallback when no decrypted messages exist yet.
            $failed = (int) $campaign->failed_count;
            if ($failed > 0) {
                $failureLabels = ['Pending diagnosis'];
                $failureValues = [$failed];
            } else {
                $failureLabels = ['No failures'];
                $failureValues = [0];
            }
        }

        return [
            'delivery' => [
                'categories' => $deliveryCategories,
                'sent'       => array_map('intval', $sentSeries),
                'delivered'  => array_map('intval', $deliveredSeries),
                'read'       => array_map('intval', $readSeries),
            ],
            'status' => [
                'labels' => ['Sent', 'Delivered', 'Read', 'Failed', 'Responded'],
                'series' => [
                    $statusFromLog['sent'],
                    $statusFromLog['delivered'],
                    $statusFromLog['read'],
                    $statusFromLog['failed'],
                    $statusFromLog['responded'],
                ],
            ],
            'throughput' => [
                'categories' => $throughputCats,
                'series'     => array_map('intval', $throughputData),
            ],
            'engagement' => [
                'categories' => $engagementCats,
                'clicks'     => array_map('intval', $clicksSeries),
                'replies'    => array_map('intval', $repliesSeries),
            ],
            'readHeatmap' => $heatmap,
            'intents'     => $intents,
            'segments'    => [
                'labels' => $segmentLabels,
                'series' => array_map('intval', $segmentValues),
            ],
            'failures' => [
                'labels' => $failureLabels,
                'series' => array_map('intval', $failureValues),
            ],
        ];
    }

    public function update(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        if (!in_array($campaign->status, ['draft', 'paused', 'scheduled'], true)) {
            $msg = 'This campaign can no longer be edited.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $msg);
        }

        $request->validate([
            'campaign_name'           => 'required|string|max:191',
            'device_id'               => 'nullable|integer',
            // Multi-engine: unified picker posts a composite `engine:id` key.
            // device_id stays accepted for back-compat (legacy single-engine form).
            'sender'                  => 'nullable|string|max:64',
            'campaign_type'           => 'required|in:text,template,button,flow,media,custom',
            'status'                  => 'nullable|string|max:32',
            'custom_message'          => 'nullable|string',
            'custom_message_b'        => 'nullable|string',
            'ab_testing'              => 'nullable|boolean',
            'ab_split'                => 'nullable|integer|min:0|max:100',
            'custom_header'           => 'nullable|string|max:255',
            'custom_footer'           => 'nullable|string|max:255',
            'custom_buttons'          => 'nullable|array',
            'custom_quick_replies'    => 'nullable|array',
            // Same positional-placeholder map the create composer emits, so
            // editing a custom body keeps the {{1}}→attribute resolution.
            'custom_message_variable_map' => 'nullable|string',
            'template_id'             => 'nullable|integer',
            'template_id_a'           => 'nullable|integer',
            'template_id_b'           => 'nullable|integer',
            'flow_id'                 => 'nullable|integer',
            'flow_id_b'               => 'nullable|integer',
            'schedule_type'           => 'required|in:now,scheduled,recurring',
            'send_date'               => 'nullable|date',
            'send_time'               => 'nullable',
            'timezone'                => ['nullable', 'string', \Illuminate\Validation\Rule::in(\DateTimeZone::listIdentifiers())],
            'repeat_interval'         => 'nullable|in:daily,weekly,monthly',
            'repeat_until'            => 'nullable|date',
            // Smart Delivery (anti-ban) — all optional; blank = global default.
            'throttle_min_sec'        => 'nullable|integer|min:0|max:3600',
            'throttle_max_sec'        => 'nullable|integer|min:0|max:3600|gte:throttle_min_sec',
            'batch_size'              => 'nullable|integer|min:1|max:10000',
            'batch_pause_min'         => 'nullable|integer|min:0|max:1440',
            'daily_limit'             => 'nullable|integer|min:1|max:100000',
            'window_start'            => 'nullable|date_format:H:i',
            'window_end'              => 'nullable|date_format:H:i',
            'recipients'              => 'nullable|array',
            'recipients.*'            => 'integer',
            'groups'                  => 'nullable|array',
            'groups.*'                => 'integer',
            // Replacing the existing attachment is optional; an edit that
            // touches no media leaves the persisted path untouched.
            'custom_image'            => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'custom_video'            => 'nullable|file|mimes:mp4|max:16384',
            'custom_document'         => 'nullable|file|mimes:pdf,doc,docx|max:16384',
        ]);

        // Named → positional normalization for the CUSTOM body — identical to
        // store() so what we persist stays canonical. Idempotent for bodies
        // that are already positional or have no tokens.
        [$normMsg, $normMap] = $this->normalizeCustomMessage(
            (string) $request->input('custom_message', ''),
            (string) $request->input('custom_message_variable_map', '')
        );

        $campaign->fill($request->only([
            'campaign_name', 'device_id', 'campaign_type', 'status',
            'custom_header', 'custom_footer', 'custom_message_b',
            'custom_buttons', 'custom_quick_replies',
            'template_id', 'template_id_a', 'template_id_b', 'flow_id', 'flow_id_b',
            'schedule_type', 'send_date', 'send_time', 'timezone',
        ]));
        $campaign->custom_message      = $normMsg;
        $campaign->custom_variable_map = $normMap;
        // A/B testing flags — not in the fill() list, so set explicitly.
        $campaign->ab_testing = (bool) $request->boolean('ab_testing');
        $campaign->ab_split   = (int) ($request->input('ab_split') ?? $campaign->ab_split ?? 50);

        // Smart Delivery (anti-ban) — persist edits; null clears back to the
        // global default. (These aren't in the fill() list above so an edit
        // would otherwise silently drop them.)
        foreach (['throttle_min_sec', 'throttle_max_sec', 'batch_size', 'batch_pause_min', 'daily_limit'] as $f) {
            $campaign->{$f} = $request->filled($f) ? (int) $request->input($f) : null;
        }
        $campaign->window_start = $request->filled('window_start') ? substr((string) $request->input('window_start'), 0, 5) : null;
        $campaign->window_end   = $request->filled('window_end') ? substr((string) $request->input('window_end'), 0, 5) : null;
        // Never persist an empty timezone — active-hours windows must resolve in
        // the workspace's local tz, not silently in UTC.
        if (empty($campaign->timezone)) {
            $campaign->timezone = optional($request->user()?->currentWorkspace)->timezone ?: config('app.timezone', 'UTC');
        }

        // Multi-engine: honor a sender changed via the unified picker (composite
        // engine:id key). Set device_id + provider together so an edit that
        // switches engines re-routes the campaign. No sender key → device_id
        // stays whatever the fill() above kept (legacy single-engine edit).
        if ($request->filled('sender')) {
            $picked = \App\Services\WorkspaceEngine::senderForKey($campaign->workspace_id, $request->input('sender'));
            if ($picked) {
                $campaign->device_id = (int) $picked['id'];
                $campaign->provider  = (string) $picked['engine'];
            }
        }

        $scheduleType = (string) $request->input('schedule_type');
        // Recurring cadence — only meaningful when the campaign repeats.
        $campaign->repeat_interval = $scheduleType === 'recurring'
            ? ($request->input('repeat_interval') ?: 'weekly') : null;
        $campaign->repeat_until = $scheduleType === 'recurring'
            ? $request->input('repeat_until') : null;

        // Optional attachment swap — first non-empty of image/video/document
        // wins, mirroring store(). A missing file leaves the stored path as-is.
        if ($request->hasFile('custom_image')) {
            $campaign->custom_image    = $request->file('custom_image')->store('campaign-media', media_disk());
            $campaign->custom_video    = null;
            $campaign->custom_document = null;
        } elseif ($request->hasFile('custom_video')) {
            $campaign->custom_video    = $request->file('custom_video')->store('campaign-media', media_disk());
            $campaign->custom_image    = null;
            $campaign->custom_document = null;
        } elseif ($request->hasFile('custom_document')) {
            $campaign->custom_document = $request->file('custom_document')->store('campaign-media', media_disk());
            $campaign->custom_image    = null;
            $campaign->custom_video    = null;
        }

        $campaign->save();

        // Recipient sync — only when the form actually carried an audience
        // selection (the HTML edit form does; the legacy JSON path may not).
        // Rebuild the per-contact log from the union of picked contacts +
        // group members so the campaign always reflects the current choice.
        if ($request->has('recipients') || $request->has('groups')) {
            $contactIds = collect($request->input('recipients', []))->map(fn ($v) => (int) $v);
            $groupIds   = collect($request->input('groups', []))->map(fn ($v) => (string) $v);

            if ($groupIds->isNotEmpty()) {
                $groupMembers = Contact::query()
                    ->forCurrentWorkspace()
                    ->get(['id', 'contact_group'])
                    ->filter(function ($c) use ($groupIds) {
                        $list = is_array($c->contact_group) ? $c->contact_group : [];
                        foreach ($list as $gid) {
                            if ($groupIds->contains((string) $gid)) return true;
                        }
                        return false;
                    })
                    ->pluck('id');
                $contactIds = $contactIds->merge($groupMembers)->unique()->values();
            }

            $contactIds = $contactIds->unique()->values();
            if ($contactIds->isNotEmpty()) {
                WpCampaignContact::where('campaign_id', $campaign->id)->delete();
                foreach ($contactIds as $cid) {
                    WpCampaignContact::create([
                        'campaign_id' => $campaign->id,
                        'contact_id'  => $cid,
                        'status'      => 'queued',
                    ]);
                }
                $campaign->total_recipients = $contactIds->count();
                $campaign->save();
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'message'  => 'Campaign updated.',
                'campaign' => $campaign,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', 'Campaign updated.');
    }

    // -----------------------------------------------------------------
    // Lifecycle actions
    // -----------------------------------------------------------------

    public function destroy(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        // Cascade-delete recipient log rows (no FK constraint on the table).
        WpCampaignContact::where('campaign_id', $campaign->id)->delete();
        $campaign->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Campaign deleted.',
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.index')->with('status', 'Campaign deleted.');
    }

    public function cancel(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        $campaign->status = 'cancelled';
        $campaign->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Campaign cancelled.',
                'status'  => $campaign->status,
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', 'Campaign cancelled.');
    }

    public function resume(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        $campaign->status = 'running';
        $campaign->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Campaign resumed.',
                'status'  => $campaign->status,
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', 'Campaign resumed.');
    }

    public function sendNow(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        $campaign->status    = 'running';
        $campaign->send_date = Carbon::now()->toDateString();
        $campaign->send_time = Carbon::now()->format('H:i:s');
        $campaign->save();

        // Pull the contact ids from the queued log rows and fire each
        // through the dispatcher. Reuses the same helper as the "now"
        // path in store() so behaviour is identical.
        $contactIds = WpCampaignContact::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('contact_id')
            ->all();
        $this->dispatchCampaignNow($campaign, $contactIds, $campaign->campaign_type, [
            'template_id'          => $campaign->template_id,
            'custom_message'       => $campaign->custom_message,
            'custom_header'        => $campaign->custom_header,
            'custom_footer'        => $campaign->custom_footer,
            'custom_buttons'       => $campaign->custom_buttons,
            'custom_quick_replies' => $campaign->custom_quick_replies,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Campaign is sending now.',
                'status'  => $campaign->status,
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', 'Campaign is sending now.');
    }

    /**
     * POST /wa-campaigns/{id}/resend — re-run a campaign that already finished
     * (completed / failed / cancelled) WITHOUT cloning the row. Every recipient
     * log row is reset to 'queued', the aggregate counters are zeroed, and the
     * campaign is dispatched again exactly the way the create + sweeper paths
     * do — reusing fireScheduledCampaign's payload shape so all custom fields
     * (header/footer/buttons/variable_map/media) ride along.
     *
     * Billing is NOT bypassed: dispatchCampaignNow runs OverflowBilling per
     * send, identical to the first run. A running campaign can't be resent —
     * it's still in flight.
     */
    public function resend(Request $request, $id)
    {
        $campaign = WpCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        // Guard: only re-run a finished campaign. A running one is in flight.
        if (!in_array($campaign->status, ['completed', 'failed', 'cancelled'], true)) {
            $msg = 'Only completed, failed or cancelled campaigns can be resent.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $msg);
        }

        // Reset the per-recipient log back to queued and clear the prior
        // send artefacts so the re-run starts from a clean slate.
        WpCampaignContact::where('campaign_id', $campaign->id)->update([
            'status'              => 'queued',
            'sent_at'             => null,
            'whatsapp_message_id' => null,
            'error_message'       => null,
        ]);

        // Zero the aggregate counters — they're rebuilt as the re-run sends.
        $campaign->sent_count      = 0;
        $campaign->failed_count    = 0;
        $campaign->delivered_count = 0;
        $campaign->read_count      = 0;
        $campaign->responded_count = 0;
        $campaign->clicked_count   = 0;
        $campaign->completed_at    = null;

        $scheduleType = (string) $campaign->schedule_type;

        if ($scheduleType === 'now') {
            // Immediate re-run — same path the "Send now" button + store()'s
            // now-branch use. dispatchCampaignNow flips status to running and
            // completes the campaign itself.
            $campaign->status      = 'running';
            $campaign->last_run_at = now();
            $campaign->save();

            $contactIds = WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->pluck('contact_id')
                ->all();

            // Payload shape mirrors fireScheduledCampaign exactly so every
            // custom field (incl. the variable map) rides along — no second,
            // divergent dispatch.
            $this->dispatchCampaignNow($campaign, $contactIds, $campaign->campaign_type, [
                'template_id'          => $campaign->template_id,
                'custom_message'       => $campaign->custom_message,
                'custom_header'        => $campaign->custom_header,
                'custom_footer'        => $campaign->custom_footer,
                'custom_buttons'       => $campaign->custom_buttons,
                'custom_quick_replies' => $campaign->custom_quick_replies,
                'custom_variable_map'  => $campaign->custom_variable_map,
            ]);
        } else {
            // Scheduled / recurring — hand it back to the sweeper. Reset the
            // status so fireScheduledCampaign picks it up at its send window.
            $campaign->status = 'scheduled';
            $campaign->save();
        }

        $msg = 'Campaign re-queued — sending again.';
        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => $msg,
                'status'  => $campaign->status,
                'id'      => (int) $id,
            ]);
        }

        return redirect()->route('user.wa-campaigns.detail', $campaign->id)->with('status', $msg);
    }

    /**
     * Fire a DUE scheduled / recurring campaign from the sweeper. There is no
     * logged-in user in that context, so we pin the campaign's creator +
     * workspace in-memory (never saved) — this is what `forCurrentWorkspace()`
     * (sender device) and the wallet charge resolve against. Reuses the exact
     * same dispatchCampaignNow path as the "Send now" button, so behaviour is
     * identical to a manual send.
     */
    public function fireScheduledCampaign(WpCampaign $campaign): void
    {
        $actor    = $campaign->created_by ? \App\Models\User::find($campaign->created_by) : null;
        $previous = Auth::user();
        if ($actor) {
            $actor->current_workspace_id = $campaign->workspace_id;   // in-memory pin only
            Auth::setUser($actor);
        }

        try {
            Log::warning('[CAMPAIGN TRACE] fireScheduledCampaign START', [
                'campaign_id'   => $campaign->id,
                'name'          => $campaign->campaign_name,
                'type'          => $campaign->campaign_type,
                'schedule_type' => $campaign->schedule_type,
                'workspace_id'  => $campaign->workspace_id,
                'actor_id'      => $actor?->id,
                'device_id'     => $campaign->device_id,
                'send_date'     => (string) $campaign->send_date,
                'send_time'     => (string) $campaign->send_time,
            ]);

            $campaign->status      = 'running';
            $campaign->last_run_at = now();
            $campaign->save();

            // Drop NULL contact_ids defensively. The web /wa-campaigns store
            // path always pre-resolves contacts so every row has a non-null
            // contact_id, but the mobile API path used to allow null when
            // auto-create failed (now hard-fails in the API store) — this
            // filter also covers legacy rows that pre-date the parity fix.
            // Without it, `Contact::whereIn('id', [null, ...])` in
            // runCampaignNowPaced silently matches zero rows and the
            // campaign reports "Campaign is being sent" but never dispatches.
            $allLogRows = WpCampaignContact::query()
                ->where('campaign_id', $campaign->id)
                ->get(['id', 'contact_id', 'phone_number']);
            $contactIds = $allLogRows->pluck('contact_id')->filter()->values()->all();
            $nullRows   = $allLogRows->whereNull('contact_id')->count();

            Log::warning('[CAMPAIGN TRACE] loaded recipients', [
                'campaign_id'        => $campaign->id,
                'recipients'         => count($contactIds),
                'null_contact_rows'  => $nullRows,
                'total_log_rows'     => $allLogRows->count(),
            ]);
            if ($nullRows > 0) {
                Log::warning('[CAMPAIGN TRACE] dropped rows with NULL contact_id — pre-resolve recipients on create', [
                    'campaign_id'      => $campaign->id,
                    'null_phone_sample'=> $allLogRows->whereNull('contact_id')->take(3)->pluck('phone_number')->all(),
                ]);
            }

            $this->dispatchCampaignNow($campaign, $contactIds, $campaign->campaign_type, [
                'template_id'          => $campaign->template_id,
                'custom_message'       => $campaign->custom_message,
                'custom_header'        => $campaign->custom_header,
                'custom_footer'        => $campaign->custom_footer,
                'custom_buttons'       => $campaign->custom_buttons,
                'custom_quick_replies' => $campaign->custom_quick_replies,
                // Without this, scheduled/recurring custom sends ship a literal {{1}}.
                'custom_variable_map'  => $campaign->custom_variable_map,
            ]);

            $campaign->refresh();
            Log::warning('[CAMPAIGN TRACE] dispatch finished', [
                'campaign_id'  => $campaign->id,
                'status'       => $campaign->status,
                'sent_count'   => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
            ]);

            // Recurring → advance one cadence + re-queue so it fires again;
            // otherwise dispatchCampaignNow already completed it (one-shot).
            if ($campaign->schedule_type === 'recurring') {
                $advanced = $campaign->advanceRecurring();
                Log::warning('[CAMPAIGN TRACE] recurring re-arm', [
                    'campaign_id' => $campaign->id,
                    'advanced'    => $advanced,
                    'next_date'   => (string) $campaign->send_date,
                    'next_time'   => (string) $campaign->send_time,
                ]);
                if ($advanced) {
                    // Fresh cadence — reset the per-recipient send state INCLUDING
                    // the retry counter + backoff, otherwise a recipient that
                    // exhausted its retries last cadence would be skipped as
                    // "terminal" on this brand-new occurrence.
                    WpCampaignContact::where('campaign_id', $campaign->id)
                        ->update(['status' => 'queued', 'send_attempts' => 0, 'next_attempt_at' => null]);
                    $campaign->status = 'scheduled';
                    $campaign->save();
                }
            }
        } finally {
            if ($actor) {
                if ($previous) {
                    Auth::setUser($previous);   // restore the real user (real request path)
                } else {
                    // Node heartbeat has no session — clear the pin so this actor's
                    // identity can't leak into the next campaign in the same sweep.
                    try { Auth::forgetUser(); } catch (\Throwable $e) { /* guard lacks forgetUser */ }
                }
            }
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);
        $ids = $request->input('ids', []);

        // Constrain delete to the current workspace so a forged payload
        // can't reach into another tenant's campaigns.
        $ownedIds = WpCampaign::query()
            ->forCurrentWorkspace()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        WpCampaignContact::whereIn('campaign_id', $ownedIds)->delete();
        WpCampaign::whereIn('id', $ownedIds)->delete();
        $ids = $ownedIds;

        $count   = count($ids);
        $message = "Deleted {$count} campaign" . ($count === 1 ? '' : 's') . '.';

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => $message,
                'ids'     => array_map('intval', $ids),
            ]);
        }

        return redirect()->route('user.wa-campaigns.index')->with('status', $message);
    }

    // -----------------------------------------------------------------
    // Build-with-AI
    // -----------------------------------------------------------------

    /**
     * GET /wa-campaigns/api/ai-models — list admin-enabled text models.
     * Mirrors the picker on /templates and /meta-ads so the UX matches.
     */
    public function apiAiModels(): JsonResponse
    {
        $rows = \DB::table('admin_ai_keys')
            ->where('is_active', true)
            ->whereNotIn('provider', ['elevenlabs'])
            ->orderBy('sort_order')
            ->get(['provider', 'name', 'default_model', 'extra_config']);

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

        // BYOK — the workspace's OWN active AI keys ALSO appear and get used, so
        // the picker offers admin-enabled providers OR the user's own key.
        $ws = auth()->user()?->current_workspace_id
            ? \App\Models\Workspace::find(auth()->user()->current_workspace_id)
            : null;
        if ($ws) {
            $byokDefaults = [
                'openai'    => ['gpt-4o-mini', 'gpt-4o'],
                'anthropic' => ['claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
                'gemini'    => ['gemini-2.0-flash', 'gemini-1.5-pro'],
                'mistral'   => ['mistral-large-latest', 'mistral-small-latest'],
            ];
            $own = \App\Models\AiProviderKey::query()
                ->where('workspace_id', $ws->id)->where('is_active', true)
                ->pluck('provider')->all();
            foreach ($own as $prov) {
                // Workspace has its OWN key for this provider → drop the admin's
                // models for it so ONLY the user's key shows (not both).
                $models = array_values(array_filter($models, fn ($mm) => $mm['provider'] !== $prov));
                $plabel = $providerLabel[$prov] ?? ucfirst($prov);
                foreach (($byokDefaults[$prov] ?? []) as $m) {
                    $models[] = ['value' => $m, 'label' => $plabel . ' (your key) · ' . $m, 'provider' => $prov];
                }
            }
        }

        return response()->json(['ok' => true, 'models' => $models]);
    }

    /**
     * POST /wa-campaigns/api/ai-generate — generate WhatsApp campaign
     * copy from a structured brief. Returns campaign_name, body
     * message, optional footer, primary CTA button + URL, and up to
     * three quick-reply labels. The front-end pastes the response
     * into the existing #campaignForm inputs.
     */
    public function apiAiGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model'              => 'required|string|max:120',
            'provider'           => 'required|string|in:openai,anthropic,gemini',
            'business_name'      => 'required|string|max:191',
            'product'            => 'nullable|string|max:255',
            'goal'               => 'nullable|string|max:120',
            'audience'           => 'nullable|string|max:500',
            'offer'              => 'nullable|string|max:500',
            'cta_label'          => 'nullable|string|max:60',
            'cta_url'            => 'nullable|string|max:1024',
            'tone'               => 'nullable|string|max:60',
            'custom_prompt'      => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $workspace = $user?->current_workspace_id
            ? \App\Models\Workspace::find($user->current_workspace_id)
            : null;

        $resolved = \App\Services\AiKeyResolver::resolve($workspace, $data['provider']);
        if (!$resolved['key']) {
            return response()->json([
                'ok'      => false,
                'error'   => 'no_key',
                'message' => 'Admin has not enabled this provider in /admin/api-keys.',
            ], 422);
        }

        $systemPrompt = <<<'SYS'
You write WhatsApp marketing-campaign messages. Output STRICT JSON only —
no prose, no markdown, no code fences. Schema:

{
  "campaign_name": "<short lowercase slug-ish label, max 60>",
  "message":       "<the body, max 1024, plain text with optional *bold* _italic_, use {{name}} for first-name token>",
  "footer":        "<short footer line, max 60, optional, no variables>",
  "button_text":   "<primary CTA label, max 25, optional>",
  "button_url":    "<https://... destination for the CTA, optional>",
  "quick_replies": ["<max 25 chars>", "<max 25 chars>", "<max 25 chars>"]
}

Rules:
1. Respect WhatsApp Business policy — no spam wording, no all-caps
   shouting, no emojis.
2. The message should hook in the first line, explain the offer, then
   suggest the next step.
3. Use {{name}} only when personalising; never invent other tokens.
4. quick_replies is 0-3 short labels the customer can tap to respond
   (e.g. "Yes please", "Not now").
5. button_text + button_url are the CTA that opens the destination
   when the customer taps. Skip both if no URL is provided.
6. Keep tone, language, and intent consistent with the brief.
7. Output ONLY the JSON object. No explanation. No code fences.
SYS;

        $lines = [];
        $lines[] = 'Business name: ' . $data['business_name'];
        if (!empty($data['product']))   $lines[] = 'Product / service: ' . $data['product'];
        if (!empty($data['goal']))      $lines[] = 'Campaign goal: ' . $data['goal'];
        if (!empty($data['audience']))  $lines[] = 'Target audience: ' . $data['audience'];
        if (!empty($data['offer']))     $lines[] = 'Offer / hook: ' . $data['offer'];
        if (!empty($data['cta_label'])) $lines[] = 'Preferred CTA label: ' . $data['cta_label'];
        if (!empty($data['cta_url']))   $lines[] = 'CTA destination URL: ' . $data['cta_url'];
        if (!empty($data['tone']))      $lines[] = 'Tone: ' . $data['tone'];
        if (!empty($data['custom_prompt'])) {
            $lines[] = '';
            $lines[] = 'Additional notes:';
            $lines[] = $data['custom_prompt'];
        }
        $userPrompt = implode("\n", $lines);

        $ai = app(\App\Services\AiAgentService::class);
        $raw = $ai->callProvider(
            provider:     $data['provider'],
            model:        $data['model'],
            workspaceId:  (int) ($workspace?->id ?? 0),
            systemPrompt: $systemPrompt,
            userPrompt:   $userPrompt,
            maxTokens:    1200,
            temperature:  0.7,
        );

        if (!$raw) {
            return response()->json([
                'ok'      => false,
                'error'   => 'provider_failed',
                'message' => 'AI provider returned no content — check API key + model id.',
            ], 502);
        }

        $clean = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $clean, $m)) {
            $clean = trim($m[1]);
        }
        $tpl = json_decode($clean, true);
        if (!is_array($tpl)) {
            Log::warning('[AI-WaCampaign] bad JSON from model: ' . substr($raw, 0, 400));
            return response()->json([
                'ok'      => false,
                'error'   => 'bad_json',
                'message' => 'Model output was not valid JSON. Try again or refine the brief.',
                'raw'     => mb_substr($raw, 0, 600),
            ], 422);
        }

        // Hard caps so the front-end never paints something the
        // controller will reject at submit time.
        $payload = [
            'campaign_name' => mb_substr((string) ($tpl['campaign_name'] ?? ''), 0, 60),
            'message'       => mb_substr((string) ($tpl['message'] ?? ''), 0, 1024),
            'footer'        => mb_substr((string) ($tpl['footer'] ?? ''), 0, 60),
            'button_text'   => mb_substr((string) ($tpl['button_text'] ?? ''), 0, 25),
            'button_url'    => mb_substr((string) ($tpl['button_url'] ?? ''), 0, 1024),
            'quick_replies' => [],
        ];
        $qr = is_array($tpl['quick_replies'] ?? null) ? $tpl['quick_replies'] : [];
        foreach (array_slice($qr, 0, 3) as $label) {
            $payload['quick_replies'][] = mb_substr((string) $label, 0, 25);
        }

        return response()->json([
            'ok'       => true,
            'campaign' => $payload,
            'model'    => $data['model'],
        ]);
    }

    // -----------------------------------------------------------------
    // Node→Laravel status callbacks
    //
    // Mirrors BroadcastsController::nodeMessageStatus /
    // nodeBroadcastStatus. All five methods share the same auth gate
    // (X-Node-Token + hash_equals) and update wp_campaign_contacts
    // and/or the parent wpcampaigns row so the campaign detail page
    // tracks sent / delivered / read / failed in real time.
    // -----------------------------------------------------------------

    private function nodeAuthOk(Request $request): bool
    {
        $expected = node_token();
        $given    = (string) $request->header('X-Node-Token');
        return $expected !== '' && hash_equals($expected, $given);
    }

    /**
     * Node ships ISO-8601 like `2026-05-16T09:27:01.807Z` which MySQL
     * DATETIME columns reject. Parse through Carbon → canonical form.
     */
    private function parseNodeTs(?string $raw): ?string
    {
        if (!$raw) return null;
        try {
            return \Illuminate\Support\Carbon::parse($raw)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Recompute wpcampaigns aggregates from the pivot. CASE-WHEN keeps
     * the call idempotent under duplicate Node webhooks.
     */
    private function recountCampaign(WpCampaign $c): void
    {
        $row = WpCampaignContact::query()
            ->where('campaign_id', $c->id)
            ->selectRaw("SUM(CASE WHEN status IN ('sent','delivered','read') THEN 1 ELSE 0 END) AS sent_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count")
            ->selectRaw("SUM(CASE WHEN status IN ('delivered','read') THEN 1 ELSE 0 END) AS delivered_count")
            ->selectRaw("SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) AS read_count")
            ->selectRaw("SUM(CASE WHEN COALESCE(responded_at, response) IS NOT NULL AND response <> '' THEN 1 ELSE 0 END) AS responded_count")
            ->selectRaw("SUM(CASE WHEN clicked = 1 THEN 1 ELSE 0 END) AS clicked_count")
            ->first();
        $c->update([
            'sent_count'      => (int) ($row->sent_count      ?? 0),
            'failed_count'    => (int) ($row->failed_count    ?? 0),
            'delivered_count' => (int) ($row->delivered_count ?? 0),
            'read_count'      => (int) ($row->read_count      ?? 0),
            'responded_count' => (int) ($row->responded_count ?? 0),
            'clicked_count'   => (int) ($row->clicked_count   ?? 0),
        ]);
    }

    /**
     * POST /api/campaigns/update-status — parent-row aggregate
     * callback. Node ships final stats once at end-of-run (mirrors
     * broadcasts.node.broadcast-status). Schema:
     *   { campaign_id, status, total_recipients, sent_count,
     *     failed_count, delivered_count, read_count,
     *     responded_count, clicked_count }
     */
    public function nodeCampaignStatus(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'campaign_id'      => 'required|integer',
            'status'           => 'nullable|string|max:32',
            'total_recipients' => 'nullable|integer',
            'sent_count'       => 'nullable|integer',
            'failed_count'     => 'nullable|integer',
            'delivered_count'  => 'nullable|integer',
            'read_count'       => 'nullable|integer',
            'responded_count'  => 'nullable|integer',
            'clicked_count'    => 'nullable|integer',
        ]);
        $c = WpCampaign::find($data['campaign_id']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'campaign not found'], 404);

        // Trust the pivot — recount from wp_campaign_contacts rather
        // than from Node's in-memory counts. Node's snapshot can drift
        // if a callback was dropped; the pivot is the source of truth.
        $this->recountCampaign($c);
        if (!empty($data['status'])) {
            $c->update(['status' => $data['status']]);
        }
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/campaigns/update-contact-status — per-recipient
     * callback fired by Node's campaignService.updateContactStatus().
     * Schema:
     *   { campaign_id, contact_id, status, error_message?,
     *     whatsapp_message_id?, variant?, sent_at|delivered_at|read_at }
     */
    public function nodeContactStatus(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'campaign_id'         => 'required|integer',
            'contact_id'          => 'required|integer',
            'status'              => 'required|in:queued,pending,sent,delivered,read,failed,unsubscribed',
            'error_message'       => 'nullable|string|max:1024',
            'whatsapp_message_id' => 'nullable|string|max:191',
            'variant'             => 'nullable|string|max:8',
            'sent_at'             => 'nullable|date',
            'delivered_at'        => 'nullable|date',
            'read_at'             => 'nullable|date',
        ]);

        $c = WpCampaign::find($data['campaign_id']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'campaign not found'], 404);

        $updates = ['status' => $data['status'], 'updated_at' => now()];
        if (!empty($data['error_message']))       $updates['error_message']       = mb_substr($data['error_message'], 0, 1024);
        if (!empty($data['whatsapp_message_id'])) $updates['whatsapp_message_id'] = $data['whatsapp_message_id'];
        if (!empty($data['variant']))             $updates['variant']             = mb_substr($data['variant'], 0, 8);
        foreach (['sent_at', 'delivered_at', 'read_at'] as $col) {
            if ($ts = $this->parseNodeTs($data[$col] ?? null)) {
                $updates[$col] = $ts;
            }
        }

        WpCampaignContact::query()
            ->where('campaign_id', $c->id)
            ->where('contact_id',  $data['contact_id'])
            ->update($updates);

        // Webhook: campaign_contact_status_updated (this write is a mass
        // UPDATE, which bypasses model events — so emit explicitly).
        \App\Services\WebhookService::emit('campaign_contact_status_updated', [
            'workspace_id'  => $c->workspace_id,
            'user_id'       => $c->created_by,
            'campaign_id'   => $c->id,
            'campaign_name' => $c->campaign_name,
            'contact_id'    => (int) $data['contact_id'],
            'status'        => $data['status'],
            'wamid'         => $data['whatsapp_message_id'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'timestamp'     => now()->timestamp,
        ], $c->created_by);

        $this->recountCampaign($c);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/campaigns/update-status-by-id — fallback used by
     * Node's handleCampaignMessageUpdate() when the campaign isn't in
     * its in-memory map (after pm2 restart or for the chat-endpoint
     * dispatch path used by this controller). Schema:
     *   { message_id, status }
     *
     * We look up wp_campaign_contacts by whatsapp_message_id and patch
     * the status + delivered_at/read_at. Without this fallback Node
     * gives up and the delivered/read receipts never land.
     */
    public function nodeStatusByMessageId(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'message_id' => 'required|string|max:191',
            'status'     => 'required|in:queued,pending,sent,delivered,read,failed',
            'timestamp'  => 'nullable|date',
        ]);

        $row = WpCampaignContact::query()
            ->where('whatsapp_message_id', $data['message_id'])
            ->first();
        if (!$row) {
            // Not a campaign message — probably a broadcast or chat
            // reply that happened to flow through the same listener.
            return response()->json(['ok' => true, 'matched' => false]);
        }

        // Only ratchet forward — don't regress read → delivered if a
        // late callback arrives out of order.
        $rank = ['queued' => 0, 'pending' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4, 'failed' => 9];
        $cur  = $rank[$row->status] ?? 0;
        $new  = $rank[$data['status']] ?? 0;
        if ($new < $cur) {
            return response()->json(['ok' => true, 'matched' => true, 'skipped' => 'older']);
        }

        $ts = $this->parseNodeTs($data['timestamp'] ?? null) ?? now()->toDateTimeString();
        $updates = ['status' => $data['status'], 'updated_at' => now()];
        if ($data['status'] === 'sent'      && !$row->sent_at)      $updates['sent_at']      = $ts;
        if ($data['status'] === 'delivered' && !$row->delivered_at) $updates['delivered_at'] = $ts;
        if ($data['status'] === 'read'      && !$row->read_at)      $updates['read_at']      = $ts;

        $row->update($updates);

        $campaign = WpCampaign::find($row->campaign_id);
        if ($campaign) {
            $this->recountCampaign($campaign);
            \App\Services\WebhookService::emit('campaign_contact_status_updated', [
                'workspace_id'  => $campaign->workspace_id,
                'user_id'       => $campaign->created_by,
                'campaign_id'   => $campaign->id,
                'campaign_name' => $campaign->campaign_name,
                'contact_id'    => (int) $row->contact_id,
                'status'        => $data['status'],
                'wamid'         => $data['message_id'],
                'timestamp'     => now()->timestamp,
            ], $campaign->created_by);
        }

        return response()->json(['ok' => true, 'matched' => true]);
    }

    /**
     * POST /api/campaigns/track-response — fired by Node when a
     * recipient replies to a campaign message. Updates the pivot's
     * response + responded_at so the operator sees reply rate.
     */
    public function nodeTrackResponse(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'campaign_id'  => 'required|integer',
            'contact_id'   => 'required|integer',
            'response'     => 'nullable|string|max:4096',
            'responded_at' => 'nullable|date',
        ]);
        $c = WpCampaign::find($data['campaign_id']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'campaign not found'], 404);

        WpCampaignContact::query()
            ->where('campaign_id', $c->id)
            ->where('contact_id',  $data['contact_id'])
            ->update([
                'response'     => mb_substr((string) ($data['response'] ?? ''), 0, 4096),
                'responded_at' => $this->parseNodeTs($data['responded_at'] ?? null) ?? now()->toDateTimeString(),
                'updated_at'   => now(),
            ]);

        // Webhook: campaign_contact_replied (mass UPDATE bypasses model events).
        \App\Services\WebhookService::emit('campaign_contact_replied', [
            'workspace_id'  => $c->workspace_id,
            'user_id'       => $c->created_by,
            'campaign_id'   => $c->id,
            'campaign_name' => $c->campaign_name,
            'contact_id'    => (int) $data['contact_id'],
            'response'      => mb_substr((string) ($data['response'] ?? ''), 0, 4096),
            'timestamp'     => now()->timestamp,
        ], $c->created_by);

        $this->recountCampaign($c);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/campaigns/unsubscribe — Node detected a STOP/UNSUB
     * keyword in a reply. Mark the pivot row + flip contact-level
     * unsubscribe so future campaigns skip this number.
     */
    public function nodeUnsubscribe(Request $request): JsonResponse
    {
        if (!$this->nodeAuthOk($request)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        $data = $request->validate([
            'campaign_id' => 'required|integer',
            'phone'       => 'required|string|max:32',
        ]);
        $c = WpCampaign::find($data['campaign_id']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'campaign not found'], 404);

        $digits = preg_replace('/\D+/', '', (string) $data['phone']);

        // Mobile is encrypted-at-rest (non-deterministic) — it can't be queried
        // in SQL, so hydrate the campaign's pivot contacts and match the
        // decrypted number in PHP.
        $last10 = substr($digits, -10);
        $row = WpCampaignContact::query()
            ->where('campaign_id', $c->id)
            ->get()
            ->first(function ($r) use ($digits, $last10) {
                $d = preg_replace('/\D+/', '', (string) $r->phone_number);
                return $d !== '' && ($d === $digits || ($last10 !== '' && str_ends_with($d, $last10)));
            });

        if ($row) {
            $row->update([
                'status'           => 'unsubscribed',
                'is_unsubscribed'  => true,
                'unsubscribed'     => true,
                'unsubscribed_at'  => now(),
                'updated_at'       => now(),
            ]);
            // Flip the contact's workspace-level unsubscribe flag too
            // so other campaigns + broadcasts auto-skip the number.
            if ($row->contact_id) {
                $alreadyOut = (bool) optional(Contact::find($row->contact_id))->is_unsubscribed;
                Contact::query()
                    ->where('id', $row->contact_id)
                    ->update(['is_unsubscribed' => true]);

                // Webhook: contact_opt_in (covers opt-out too — STOP keyword).
                // Only fire on an actual transition, and emit explicitly since
                // this is a mass UPDATE that bypasses the Contact model events.
                if (!$alreadyOut) {
                    \App\Services\WebhookService::emit('contact_opt_in', [
                        'workspace_id' => $c->workspace_id,
                        'user_id'      => $c->created_by,
                        'contact_id'   => (int) $row->contact_id,
                        'phone_number' => preg_replace('/\D+/', '', (string) $row->phone_number) ?: null,
                        'opted_in'     => false,
                        'action'       => 'unsubscribed',
                        'source'       => 'stop_keyword',
                        'timestamp'    => now()->timestamp,
                    ], $c->created_by);
                }
            }
            $this->recountCampaign($c);
        }

        return response()->json(['ok' => true]);
    }
}
