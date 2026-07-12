<?php

namespace App\Http\Controllers;

use App\Models\MetaCampaign;
use App\Services\MetaGraphClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Meta-Ads (formerly /campaigns) controller.
 *
 * Replaces the static /campaigns prototype + the old project's
 * D:\wadesk_2806\New folder\app\Http\Controllers\CampaignController.php
 * which mixed local persistence with synchronous Facebook Graph
 * API calls. Here we own the local model only — Graph API sync
 * lands in a follow-up so the UI is testable first.
 *
 * All PII columns on MetaCampaign are encrypted-at-rest, so
 * search and name-sort happen in PHP after hydration (LIKE on
 * ciphertext returns nothing).
 */
class MetaAdsController extends Controller
{
    /**
     * Resolve the Meta Marketing Graph API client for a specific
     * campaign's workspace. Multi-tenant — each customer uses their
     * OWN connected ad account + page + WABA. Replaces the old
     * env-based singleton that leaked credentials across tenants.
     */
    private function graphFor(MetaCampaign $c): MetaGraphClient
    {
        return new MetaGraphClient($this->metaConfig((int) $c->workspace_id));
    }

    /** Resolve a client for the current workspace (for non-campaign-scoped calls like sync()). */
    private function graphForWorkspace(): MetaGraphClient
    {
        return new MetaGraphClient($this->metaConfig((int) (Auth::user()?->current_workspace_id ?? 0)));
    }

    /**
     * Resolve the WaProviderConfig that holds a workspace's Meta Ads
     * credentials. Priority:
     *   1. the dedicated provider=meta_ads row (the workspace's OWN keys
     *      entered on /meta-ads/connect),
     *   2. the provider=waba messaging row (back-compat — workspaces that
     *      connected WABA before the dedicated Meta Ads keys existed).
     * If neither has usable creds, MetaGraphClient's constructor applies
     * the admin global fallback. Returns null when the workspace has no
     * config at all (client then runs purely on admin fallback).
     */
    private function metaConfig(?int $wsId): ?\App\Models\WaProviderConfig
    {
        if (!$wsId) return null;
        return \App\Models\WaProviderConfig::query()
            ->where('workspace_id', $wsId)
            ->whereIn('provider', ['meta_ads', 'waba'])
            ->orderByRaw("CASE provider WHEN 'meta_ads' THEN 0 ELSE 1 END")
            ->orderByDesc('is_primary')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * AJAX: discover the ad accounts + Facebook Pages the workspace's connected
     * Meta/WhatsApp token can see, so the connect flow AUTO-FILLS the ad account
     * + page instead of forcing the operator to paste raw IDs after embedded
     * signup / coexistence (the token is already reused from that connection).
     * If the token can see exactly one ad account AND one page, adopt them
     * outright — Meta Ads is then ready with zero manual entry.
     */
    public function discover(Request $request)
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        if (!$wsId) return response()->json(['ok' => false, 'error' => 'no_workspace'], 403);

        $graph  = $this->graphForWorkspace();
        $assets = $graph->discoverAssets();
        $accts  = $assets['ad_accounts'] ?? [];
        $pages  = $assets['pages'] ?? [];

        // Zero-touch: exactly one of each → save straight away so the account is
        // ready immediately after embedded signup, no picker needed.
        $autofilled = false;
        if (count($accts) === 1 && count($pages) === 1) {
            $this->adoptMetaAssets($wsId, (string) $accts[0]['id'], (string) $pages[0]['id']);
            $autofilled = true;
        }

        return response()->json([
            'ok'          => (bool) ($assets['ok'] ?? false),
            'autofilled'  => $autofilled,
            'configured'  => $this->graphForWorkspace()->isConfigured(),
            'ad_accounts' => $accts,
            'pages'       => $pages,
            'error'       => $assets['error'] ?? null,
        ]);
    }

    /**
     * Persist a discovered ad account + page onto the config the client already
     * resolves (the connected WABA/embedded-signup row, or an existing meta_ads
     * row) — so its reused token + these two ids make isConfigured() true.
     */
    private function adoptMetaAssets(int $wsId, string $adAccountId, string $pageId): void
    {
        $cfg = $this->metaConfig($wsId);
        if (!$cfg) return;
        $cfg->meta_json = array_merge((array) $cfg->meta_json, [
            'fb_ad_account_id' => preg_replace('/^act_/', '', trim($adAccountId)),
            'fb_page_id'       => trim($pageId),
        ]);
        $cfg->save();

        \App\Support\Audit::log('meta_ads.assets.auto_discovered', [
            'meta' => ['workspace_id' => $wsId, 'ad_account_set' => $adAccountId !== '', 'page_set' => $pageId !== ''],
        ]);
    }

    /**
     * Resolve the Instagram professional-account id used as an ad's IG
     * identity (object_story_spec.instagram_user_id). Priority:
     *   1. the campaign's already-stored instagram_user_id,
     *   2. the workspace's connected instagram_accounts row,
     *   3. a Page-Backed Instagram Account minted from the Facebook Page
     *      (so a workspace WITHOUT a real IG account can still run IG ads).
     * Returns null when none can be resolved (the readiness gate then
     * surfaces a clear "connect Instagram" error).
     */
    private function resolveInstagramUserId(MetaCampaign $c, MetaGraphClient $graph): ?string
    {
        $stored = trim((string) ($c->instagram_user_id ?? ''));
        if ($stored !== '') return $stored;

        $wsId = (int) ($c->workspace_id ?: (Auth::user()?->current_workspace_id ?? 0));
        if ($wsId) {
            try {
                $acct = \App\Models\InstagramAccount::query()
                    ->where('workspace_id', $wsId)
                    ->whereNotNull('ig_user_id')
                    ->orderByRaw("CASE WHEN status = 'connected' THEN 0 ELSE 1 END")
                    ->orderByDesc('id')
                    ->first();
                $id = trim((string) ($acct->ig_user_id ?? ''));
                if ($id !== '') return $id;
            } catch (\Throwable $e) {
                // fall through to PBIA
            }
        }

        // No real IG account → mint / reuse a Page-Backed Instagram Account.
        return $graph->ensurePbia();
    }

    // -----------------------------------------------------------------
    // Pages
    // -----------------------------------------------------------------

    public function index(Request $request)
    {
        $userId = Auth::id();
        $status    = $request->string('status')->toString()    ?: 'all';
        $objective = $request->string('objective')->toString() ?: 'all';
        $range     = $request->string('range')->toString()     ?: 'all';
        $search    = $request->string('q')->toString();
        $sort      = $request->string('sort')->toString()      ?: 'date-desc';

        $campaigns = MetaCampaign::query()
            ->forCurrentWorkspace()
            ->withStatus($status)
            ->withObjective($objective)
            ->inRange($range)
            ->orderByDesc('created_at')
            ->get();

        $campaigns = MetaCampaign::filterByName($campaigns, $search);
        $campaigns = $this->applySort($campaigns, $sort);
        $campaigns = $this->paginateCollection($campaigns, $request, 12);

        $payload = [
            'campaigns'        => $campaigns,
            'statusCounts'     => $this->statusCounts($userId),
            'objectiveCounts'  => $this->objectiveCounts($userId),
            'totals'           => $this->totals($userId),
            'currentStatus'    => $status,
            'currentObjective' => $objective,
            'currentRange'     => $range,
            'currentSearch'    => $search,
            'currentSort'      => $sort,
        ];

        // AJAX path — same data, but only the campaign-list partial
        // and the count payloads. The JS reuses these to swap just
        // the cards + the filter-rail badges + the stat row, so
        // filter clicks / live search don't trigger a full reload.
        if ($request->wantsJson() || $request->boolean('partial')) {
            return response()->json([
                'cards'     => view('user.campaigns._cards', $payload)->render(),
                'counts'    => $payload['statusCounts'],
                'objCounts' => $payload['objectiveCounts'],
                'totals'    => $payload['totals'],
                'pagination'=> view('user.partials.pagination', ['paginator' => $campaigns, 'dataAttr' => 'data-meta-page', 'label' => 'campaigns'])->render(),
                'shown'     => $campaigns->count(),
                'total'     => $campaigns->total(),
                'page'      => $campaigns->currentPage(),
            ]);
        }

        // Merge Meta Ads key-entry data so the page can render the
        // connect modal (opened by the "Keys" button or ?connect=1).
        return view('user.campaigns.index', array_merge($payload, $this->metaConnectData()));
    }

    public function create(): View|RedirectResponse
    {
        // Gate: a campaign can't be built without Meta Ads credentials.
        // Resolve the workspace client (workspace keys → admin fallback).
        // If still unconfigured, send the operator to enter their OWN
        // keys first — "click Create → connect your account".
        if (! $this->graphForWorkspace()->isConfigured()) {
            // Bounce to the Meta Ads page with the connect modal auto-open
            // (?connect=1). The standalone /meta-ads/connect page remains a
            // deep-link fallback.
            return redirect()->route('user.meta-ads.index', ['connect' => 1])
                ->with('status', __('Connect your Meta Ads account to create a campaign.'));
        }

        return view('user.campaigns.create', [
            'interestGroups' => (array) config('meta_targeting.interests_groups', []),
            'countries'      => (array) config('meta_targeting.countries', []),
        ]);
    }

    /**
     * Show the workspace's Meta Ads key-entry screen. Pre-fills the
     * non-secret fields from the dedicated meta_ads row; the token is
     * never echoed back (only "is set" status).
     */
    public function connect(): View
    {
        return view('user.meta-ads.connect', $this->metaConnectData());
    }

    /**
     * View-data for the Meta Ads key-entry surface (the /connect page AND
     * the modal on /meta-ads). Token is never echoed back — only "is set".
     */
    private function metaConnectData(): array
    {
        $wsId  = (int) (Auth::user()?->current_workspace_id ?? 0);
        $cfg   = \App\Models\WaProviderConfig::query()
            ->where('workspace_id', $wsId)->where('provider', 'meta_ads')->first();
        $meta  = is_array($cfg?->meta_json) ? $cfg->meta_json : [];
        $creds = $cfg?->creds() ?? [];
        $fb    = MetaGraphClient::adminFallbackKeys();

        return [
            'hasToken'      => ($creds['ads_token'] ?? $creds['access_token'] ?? '') !== '',
            'adAccountId'   => (string) ($meta['fb_ad_account_id'] ?? ''),
            'pageId'        => (string) ($meta['fb_page_id'] ?? ''),
            'phone'         => (string) ($meta['display_phone_number'] ?? ''),
            'wabaId'        => (string) ($meta['waba_id'] ?? ''),
            'phoneNumberId' => (string) ($meta['phone_number_id'] ?? ''),
            // True when the platform admin has configured global fallback
            // keys, so the operator can run ads even without their own.
            'adminFallback' => ($fb['token'] !== '' && $fb['ad_account_id'] !== ''),
            'connected'     => $cfg !== null,
        ];
    }

    /**
     * Persist the workspace's OWN Meta Ads credentials onto a dedicated
     * provider=meta_ads WaProviderConfig row. Token → encrypted
     * credentials_json; the rest → meta_json. The row is NEVER marked
     * primary, so it can't be picked up by the send-engine resolver
     * (WorkspaceEngine ignores provider=meta_ads outright).
     */
    public function saveKeys(Request $request): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        if (!$wsId) abort(403, 'No workspace.');

        $data = $request->validate([
            'token'           => ['nullable', 'string', 'max:1024'],
            'ad_account_id'   => ['required', 'string', 'max:64'],
            'page_id'         => ['required', 'string', 'max:64'],
            'phone'           => ['nullable', 'string', 'max:32'],
            'waba_id'         => ['nullable', 'string', 'max:64'],
            'phone_number_id' => ['nullable', 'string', 'max:64'],
            'clear_token'     => ['nullable', 'in:0,1'],
        ]);

        $cfg = \App\Models\WaProviderConfig::firstOrNew([
            'workspace_id' => $wsId,
            'provider'     => 'meta_ads',
        ]);

        // Require a token on first connect (none stored yet).
        $creds = $cfg->creds();
        $hadToken = ($creds['ads_token'] ?? $creds['access_token'] ?? '') !== '';
        if (!empty($data['clear_token'])) {
            unset($creds['ads_token'], $creds['access_token']);
        } elseif (!empty($data['token'])) {
            $creds['ads_token'] = trim($data['token']);
        } elseif (!$hadToken) {
            // Validation-bag error (not a flash) so the inline keys modal
            // re-opens with the message — see $errors->any() in
            // user/campaigns/index.blade.php.
            return back()->withInput()
                ->withErrors(['token' => __('An access token is required to connect Meta Ads.')]);
        }
        $cfg->setCreds($creds);

        $cfg->meta_json = array_merge((array) $cfg->meta_json, [
            'fb_ad_account_id'     => preg_replace('/^act_/', '', trim((string) $data['ad_account_id'])),
            'fb_page_id'           => trim((string) $data['page_id']),
            'display_phone_number' => preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')),
            'waba_id'              => trim((string) ($data['waba_id'] ?? '')),
            'phone_number_id'      => trim((string) ($data['phone_number_id'] ?? '')),
        ]);
        $cfg->status        = \App\Models\WaProviderConfig::STATUS_CONNECTED;
        $cfg->is_primary    = false;  // never the send engine
        $cfg->display_label = 'Meta Ads';
        if (!$cfg->exists || $cfg->connected_at === null) $cfg->connected_at = now();
        $cfg->save();

        \App\Support\Audit::log('meta_ads.keys.saved', [
            'meta' => [
                'workspace_id'   => $wsId,
                'ad_account_set' => true,
                'token_changed'  => !empty($data['token']) || !empty($data['clear_token']),
            ],
        ]);

        return redirect()->route('user.meta-ads.create')
            ->with('status', __('Meta Ads account connected.'));
    }

    /** Remove the workspace's own Meta Ads keys (reverts to admin fallback). */
    public function disconnect(): RedirectResponse
    {
        $wsId = (int) (Auth::user()?->current_workspace_id ?? 0);
        \App\Models\WaProviderConfig::query()
            ->where('workspace_id', $wsId)->where('provider', 'meta_ads')->delete();
        \App\Support\Audit::log('meta_ads.keys.removed', ['meta' => ['workspace_id' => $wsId]]);
        return redirect()->route('user.meta-ads.connect')
            ->with('status', __('Meta Ads keys removed.'));
    }

    public function edit(int $id): View
    {
        $campaign = MetaCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        return view('user.campaigns.edit', [
            'campaign'       => $campaign,
            'interestGroups' => (array) config('meta_targeting.interests_groups', []),
            'countries'      => (array) config('meta_targeting.countries', []),
        ]);
    }

    public function analytics(Request $request): View
    {
        $userId = Auth::id();
        $id     = $request->integer('id');

        $picker = MetaCampaign::query()->forCurrentWorkspace()
            ->orderByDesc('status')   // ACTIVE first, then PAUSED, …
            ->orderByDesc('id')
            ->get(['id', 'name', 'status', 'optimization_goal']);

        // Two modes share the same route:
        //   /meta-ads/analytics          → workspace-wide aggregate
        //   /meta-ads/analytics?id=N     → that campaign's drill-down
        // The header "Analytics" button hits the no-id form; the
        // per-card sparkline icon hits the ?id= form. Keeps one URL
        // surface but two clearly different views.
        if ($id > 0) {
            $campaign = MetaCampaign::query()->forCurrentWorkspace()->find($id);
            return view('user.meta-ads.analytics', [
                'mode'      => 'campaign',
                'campaign'  => $campaign,
                'picker'    => $picker,
                'aggregate' => null,
            ]);
        }

        return view('user.meta-ads.analytics', [
            'mode'      => 'global',
            'campaign'  => null,
            'picker'    => $picker,
            'aggregate' => $this->aggregateInsights($userId),
        ]);
    }

    /**
     * Roll up insights across every campaign for the workspace-wide
     * analytics view. `insights` is a plain JSON column (not
     * encrypted — performance metrics aren't PII), so a quick
     * in-PHP reduce is cheap and beats writing JSON_EXTRACT
     * variants for every supported DB.
     */
    private function aggregateInsights(?int $userId): array
    {
        $items = MetaCampaign::query()->forCurrentWorkspace()
            ->get(['id', 'name', 'status', 'optimization_goal', 'daily_budget', 'insights', 'created_at']);

        $sum = function (string $key) use ($items) {
            return $items->sum(fn ($c) => (float) ($c->insights[$key] ?? 0));
        };
        $sumI = function (string $key) use ($items) {
            return (int) $items->sum(fn ($c) => (int) ($c->insights[$key] ?? 0));
        };

        $spend       = round($sum('spend'), 2);
        $revenue     = round($sum('revenue'), 2);
        $impressions = $sumI('impressions');
        $reach       = $sumI('reach');
        $clicks      = $sumI('clicks');
        $conversions = $sumI('conversions');

        $byStatus = $items->groupBy('status')->map->count()->all();
        $byGoal   = $items->groupBy('optimization_goal')
            ->map(fn ($g) => [
                'count'  => $g->count(),
                'spend'  => round($g->sum(fn ($c) => (float) ($c->insights['spend']   ?? 0)), 2),
                'leads'  => (int) $g->sum(fn ($c) => (int)   ($c->insights['conversions'] ?? 0)),
            ])
            ->all();

        // Top performers by ROAS (revenue / spend) — useful for the
        // "where to scale" panel.
        $top = $items
            ->map(function ($c) {
                $s = (float) ($c->insights['spend']   ?? 0);
                $r = (float) ($c->insights['revenue'] ?? 0);
                return [
                    'id'    => $c->id,
                    'name'  => $c->name,
                    'goal'  => $c->optimization_goal,
                    'spend' => round($s, 2),
                    'roas'  => $s > 0 ? round($r / $s, 2) : 0,
                    'leads' => (int) ($c->insights['conversions'] ?? 0),
                    'status'=> $c->status,
                ];
            })
            ->sortByDesc('roas')
            ->values()
            ->take(5)
            ->all();

        return [
            'total_campaigns' => $items->count(),
            'active'          => (int) ($byStatus['ACTIVE'] ?? 0),
            'paused'          => (int) ($byStatus['PAUSED'] ?? 0),
            'scheduled'       => (int) ($byStatus['SCHEDULED'] ?? 0),
            'spend'           => $spend,
            'revenue'         => $revenue,
            'roas'            => $spend > 0 ? round($revenue / $spend, 2) : 0,
            'impressions'     => $impressions,
            'reach'           => $reach,
            'clicks'          => $clicks,
            'conversions'     => $conversions,
            'ctr'             => $impressions ? round($clicks / max($impressions, 1) * 100, 2) : 0,
            'cpc'             => $clicks      ? round($spend  / max($clicks,      1),      2) : 0,
            'cpl'             => $conversions ? round($spend  / max($conversions, 1),      2) : 0,
            'by_goal'         => $byGoal,
            'top'             => $top,
        ];
    }

    // -----------------------------------------------------------------
    // Mutations
    // -----------------------------------------------------------------

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCampaign($request);

        $imagePath = null;
        if ($request->hasFile('creative_image_file')) {
            $imagePath = $request->file('creative_image_file')->store('meta-campaigns', 'public');
        }

        $columns = $this->mapValidatedToColumns($data);
        $chosenStatus = strtoupper((string) ($columns['status'] ?? 'PAUSED'));
        // DRAFT → don't push to Meta, just save locally. ACTIVE/PAUSED →
        // push the 5-step CTWA tree; on success we ACTIVATE if user asked.
        $columns['status'] = $chosenStatus === 'DRAFT' ? 'DRAFT' : 'PAUSED';

        $campaign = MetaCampaign::create(array_merge($columns, [
            'user_id'        => Auth::id(),
            'creative_image' => $imagePath,
            'insights'       => [],
        ]));

        // Push to Meta via the FULL 5-step CTWA flow when:
        //   (a) the admin has flipped meta_ads_enabled ON, AND
        //   (b) the user picked ACTIVE or PAUSED (not DRAFT), AND
        //   (c) the workspace's WaProviderConfig has the required IDs.
        // Drafts stay local-only; the customer can edit + save again
        // to publish later.
        $synced = false;
        if ($chosenStatus !== 'DRAFT' && \App\Models\SystemSetting::get('meta_ads_enabled', false)) {
            $this->syncToMeta($campaign);
            $synced = (bool) $campaign->facebook_id;

            // If the user asked for ACTIVE and the sync produced live
            // entities, flip the cascade. Otherwise stay PAUSED.
            if ($synced && $chosenStatus === 'ACTIVE') {
                if ($this->graphFor($campaign)->setStatusCascade($campaign, 'ACTIVE')) {
                    $campaign->status = 'ACTIVE';
                    $campaign->save();
                }
            }
        }

        // JSON shape — gives the front-end the meta IDs + sync state so
        // it can update the row inline without a full reload.
        if ($request->wantsJson() || $request->boolean('ajax')) {
            return response()->json([
                'ok'              => true,
                'data'            => [
                    'id'               => $campaign->id,
                    'name'             => $campaign->name,
                    'status'           => $campaign->status,
                    'facebook_id'      => $campaign->facebook_id,
                    'meta_adset_id'    => $campaign->meta_adset_id,
                    'meta_creative_id' => $campaign->meta_creative_id,
                    'meta_ad_id'       => $campaign->meta_ad_id,
                    'meta_last_error'  => $campaign->meta_last_error,
                    'synced'           => $synced,
                ],
                'redirect' => route('user.meta-ads.show', $campaign->id),
            ]);
        }

        $msg = match (true) {
            $chosenStatus === 'DRAFT'        => 'Campaign "' . $campaign->name . '" saved as draft.',
            $synced && $campaign->status === 'ACTIVE'  => 'Campaign "' . $campaign->name . '" launched live on Meta.',
            $synced                          => 'Campaign "' . $campaign->name . '" created on Meta (paused — flip the toggle to go live).',
            default                          => 'Campaign "' . $campaign->name . '" saved locally. ' . mb_substr((string) $campaign->meta_last_error, 0, 200),
        };
        return redirect()->route('user.meta-ads.show', $campaign->id)->with('status', $msg);
    }

    /**
     * Detail page — shown after create + from the "View" CTA on the
     * index card. Lays out the full Meta entity tree (campaign_id,
     * adset_id, creative_id, ad_id), insights snapshot, and the last
     * Meta error if sync failed. Auto-polls insights every 60s while
     * status is ACTIVE.
     */
    public function show(int $id): View
    {
        $campaign = MetaCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        $graph = $this->graphFor($campaign);

        return view('user.campaigns.show', [
            'campaign'    => $campaign,
            'graph'       => $graph,
            'isCtwaReady' => $graph->isCtwaReady(),
        ]);
    }

    /**
     * Refresh insights + status for ONE campaign — used by the detail
     * page poll. Returns JSON so the front-end can update inline.
     */
    public function refresh(int $id): JsonResponse
    {
        $c = MetaCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        if (!$c->facebook_id) {
            return response()->json([
                'ok'          => true,
                'meta_status' => 'DRAFT',
                'insights'    => $c->insights ?? [],
                'last_error'  => $c->meta_last_error,
            ]);
        }

        $graph = $this->graphFor($c);
        if ($graph->isConfigured()) {
            $insights = $graph->fetchInsights($c->facebook_id);
            if (!empty($insights)) {
                $c->update(['insights' => $insights, 'meta_synced_at' => now()]);
            }
        }

        return response()->json([
            'ok'           => true,
            'meta_status'  => $c->status,
            'insights'     => $c->insights ?? [],
            'last_synced'  => optional($c->meta_synced_at)->diffForHumans(),
            'last_error'   => $c->meta_last_error,
            'meta_ids'     => [
                'campaign' => $c->facebook_id,
                'adset'    => $c->meta_adset_id,
                'creative' => $c->meta_creative_id,
                'ad'       => $c->meta_ad_id,
            ],
        ]);
    }

    /**
     * Retry a FAILED sync — runs the 5-step pipeline again for one
     * campaign. Most useful right after the customer fixed the issue
     * surfaced by `meta_last_error` (e.g. missing page_id, low-res
     * image, no ads_management scope).
     */
    public function retry(int $id): JsonResponse
    {
        $c = MetaCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        // If the campaign already has Meta IDs, nuke the partial tree
        // first so we don't end up with two parallel hierarchies on
        // the customer's ad account.
        if ($c->facebook_id || $c->meta_ad_id) {
            $graph = $this->graphFor($c);
            if ($graph->isConfigured()) $graph->deleteCascade($c);
            $c->update([
                'facebook_id' => null, 'meta_adset_id' => null,
                'meta_creative_id' => null, 'meta_ad_id' => null,
                'meta_image_hash' => null,
            ]);
        }

        $this->syncToMeta($c);
        $c->refresh();

        return response()->json([
            'ok'         => (bool) $c->facebook_id,
            'status'     => $c->status,
            'last_error' => $c->meta_last_error,
            'meta_ids'   => [
                'campaign' => $c->facebook_id,
                'adset'    => $c->meta_adset_id,
                'creative' => $c->meta_creative_id,
                'ad'       => $c->meta_ad_id,
            ],
        ]);
    }

    /**
     * Execute the full Click-to-WhatsApp pipeline. On partial failure
     * we record the error and rollback the entities we DID create on
     * Meta's side — leaving orphan ad sets inside a customer's ad
     * account is a recipe for billing complaints.
     *
     * Sequence:
     *   1. Resolve workspace's Meta + WABA config
     *   2. Upload creative image → image_hash
     *   3. POST /campaigns      → campaign_id
     *   4. POST /adsets         → adset_id
     *   5. POST /adcreatives    → creative_id
     *   6. POST /ads            → ad_id
     *
     * Each id is persisted onto meta_campaigns so subsequent toggle
     * / delete operations cascade correctly.
     */
    private function syncToMeta(MetaCampaign $c): void
    {
        $graph = $this->graphFor($c);
        if (!$graph->isConfigured()) {
            $c->update(['meta_last_error' => 'Meta Ads not configured for this workspace. Connect at /devices first.']);
            return;
        }

        $adType = $c->adType();

        // Instagram identity — needed for Click-to-Instagram-Direct or any
        // ad whose placement includes Instagram. Resolve from the connected
        // IG account (or a Page-Backed Instagram Account) and inject it +
        // persist for display, BEFORE the readiness gate.
        if ($adType === \App\Models\MetaCampaign::AD_TYPE_IG_DIRECT || $c->wantsInstagram()) {
            $igId = $this->resolveInstagramUserId($c, $graph);
            if ($igId) {
                $graph->withInstagramUserId($igId);
                if ((string) $c->instagram_user_id !== (string) $igId) {
                    $c->update(['instagram_user_id' => $igId]);
                }
            }
        }

        // Readiness gate by ad type / intent.
        if ($adType === \App\Models\MetaCampaign::AD_TYPE_CTWA) {
            if (!$graph->isCtwaReady()) {
                $c->update(['meta_last_error' => 'Click-to-WhatsApp needs fb_page_id + waba_id + phone_number_id on this workspace. Add them at /devices.']);
                return;
            }
        } elseif ($adType === \App\Models\MetaCampaign::AD_TYPE_IG_DIRECT || $c->wantsInstagram()) {
            // Click-to-Instagram-Direct, or a link ad explicitly placed on
            // Instagram → needs an Instagram identity.
            if (!$graph->isInstagramReady()) {
                $c->update(['meta_last_error' => 'Instagram ads need a connected Facebook Page + an Instagram professional account. Connect Instagram at /instagram (or ensure your Page has a linked IG account).']);
                return;
            }
        } else {
            // Facebook-only link ad → just needs a configured account + a
            // Page (page_id is mandatory in object_story_spec).
            if ($graph->pageId() === null) {
                $c->update(['meta_last_error' => 'This ad needs a connected Facebook Page. Add it at /devices.']);
                return;
            }
        }

        // Local image path is REQUIRED for the creative upload step.
        if (empty($c->creative_image)) {
            $c->update(['meta_last_error' => 'Ad creative requires an uploaded image (1080×1080+).']);
            return;
        }

        $imagePath = storage_path('app/public/' . ltrim($c->creative_image, '/'));
        $campaignId  = null;
        $adSetId     = null;
        $creativeId  = null;
        $adId        = null;
        $imageHash   = null;

        try {
            $imageHash  = $graph->uploadImage($imagePath);
            $campaignId = $graph->createCampaign($c);
            $adSetId    = $graph->createAdSet($c, $campaignId);
            $creativeId = $graph->createAdCreative($c, $imageHash);
            $adId       = $graph->createAd($c, $adSetId, $creativeId);

            $c->update([
                'facebook_id'      => $campaignId,
                'meta_adset_id'    => $adSetId,
                'meta_creative_id' => $creativeId,
                'meta_ad_id'       => $adId,
                'meta_image_hash'  => $imageHash,
                'meta_synced_at'   => now(),
                'meta_last_error'  => null,
                // Clear FAILED status when retry succeeds. We don't
                // touch ACTIVE/PAUSED because store() may have set
                // them deliberately based on the user's chosen status.
                'status'           => $c->status === 'FAILED' ? 'PAUSED' : $c->status,
            ]);

            Log::info('[META-ADS] sync ok', [
                'campaign_id' => $c->id,
                'fb_campaign' => $campaignId,
                'adset'       => $adSetId,
                'creative'    => $creativeId,
                'ad'          => $adId,
            ]);
        } catch (\Throwable $e) {
            $errMsg = $e->getMessage();
            Log::warning('[META-ADS] sync failed — rolling back', [
                'campaign_id' => $c->id,
                'error'       => $errMsg,
                'meta_last'   => $graph->lastError,
            ]);

            // Rollback in reverse order. Best-effort — losing a remote
            // orphan is worse than blocking the user's UI. Stash the
            // partial ids onto the model so deleteCascade can walk the
            // tree symmetrically (Ad → Creative → Ad Set → Campaign).
            $c->forceFill([
                'meta_ad_id'       => $adId       ?: $c->meta_ad_id,
                'meta_creative_id' => $creativeId ?: $c->meta_creative_id,
                'meta_adset_id'    => $adSetId    ?: $c->meta_adset_id,
                'facebook_id'      => $campaignId ?: $c->facebook_id,
            ]);
            try { $graph->deleteCascade($c); } catch (\Throwable $_) {}

            $c->update([
                'status'           => 'FAILED',
                'meta_last_error'  => mb_substr($errMsg, 0, 500),
                'meta_synced_at'   => now(),
                // Persist any IDs we DID get back so the customer can
                // see partial progress in /meta-ads/{id} detail page.
                'facebook_id'      => $campaignId,
                'meta_adset_id'    => $adSetId,
                'meta_creative_id' => $creativeId,
                'meta_ad_id'       => $adId,
                'meta_image_hash'  => $imageHash,
            ]);
        }
    }

    public function update(Request $request, int $id)
    {
        $campaign  = MetaCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        $data      = $this->validateCampaign($request, updating: true);
        // Preserve the existing ad type when the edit request didn't post it,
        // so the objective (and IG gating) aren't recomputed against the
        // 'ctwa' default — which would clobber an existing link/IG-Direct ad.
        if (! array_key_exists('ad_type', $data) || ($data['ad_type'] ?? null) === null) {
            $data['ad_type'] = $campaign->ad_type ?: \App\Models\MetaCampaign::AD_TYPE_CTWA;
        }
        $imageChanged = $request->hasFile('creative_image_file');

        if ($imageChanged) {
            // Replacing the creative image — delete the previous file
            // from local storage so the bucket doesn't accumulate
            // orphaned uploads on every edit.
            if ($campaign->creative_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($campaign->creative_image);
            }
            $campaign->creative_image = $request->file('creative_image_file')->store('meta-campaigns', 'public');
        }

        $campaign->fill($this->mapValidatedToColumns($data))->save();

        // Re-sync to Meta when the campaign already exists there.
        // Without this, customers' edits stay local-only — they see
        // "saved" but Meta still serves the OLD creative + budget +
        // targeting.
        //
        // Strategy: rebuild the full tree (cheaper + cleaner than
        // patching individual entities since Meta's update endpoints
        // are inconsistent). Retry() already handles the
        // "tear-down + 5-step replay" pattern.
        $alreadyOnMeta = $campaign->facebook_id && \App\Models\SystemSetting::get('meta_ads_enabled', false);
        if ($alreadyOnMeta && $campaign->status !== 'DRAFT') {
            $graph = $this->graphFor($campaign);
            if ($graph->isConfigured()) {
                // Tear down the old Meta tree (best-effort) then rebuild.
                $graph->deleteCascade($campaign);
                $campaign->update([
                    'facebook_id'      => null,
                    'meta_adset_id'    => null,
                    'meta_creative_id' => null,
                    'meta_ad_id'       => null,
                    'meta_image_hash'  => null,
                ]);
                $this->syncToMeta($campaign);
            }
        }

        $statusMsg = 'Campaign "' . $campaign->name . '" updated.'
            . ($alreadyOnMeta && !$campaign->facebook_id
                ? ' Meta re-sync failed: ' . mb_substr((string) $campaign->meta_last_error, 0, 200)
                : '');

        // AJAX edit submits expect JSON so the page can update inline
        // without a hard reload (same pattern as store()). HTML submits
        // still get the redirect for legacy/server-rendered fallbacks.
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'       => true,
                'campaign' => $campaign->fresh(),
                'message'  => $statusMsg,
                'meta_resynced' => (bool) ($alreadyOnMeta && $campaign->facebook_id),
            ]);
        }

        return redirect()->route('user.meta-ads.show', $campaign->id)->with('status', $statusMsg);
    }

    public function destroy(int $id): JsonResponse
    {
        $c = MetaCampaign::query()->forCurrentWorkspace()->findOrFail($id);

        // Best-effort cascade-delete on Meta first — losing a remote
        // entity is worse than leaving a stale local row, and a failed
        // remote delete shouldn't block the local cleanup. Cascade
        // walks the full Ad → Creative → Ad Set → Campaign tree so
        // we don't orphan child entities inside the customer's ad
        // account.
        if ($c->facebook_id || $c->meta_ad_id) {
            $graph = $this->graphFor($c);
            if ($graph->isConfigured()) {
                $graph->deleteCascade($c);
            }
        }

        // Delete the local creative image too — Meta has its own copy
        // (image_hash) so the customer's bucket doesn't need it anymore.
        if ($c->creative_image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($c->creative_image);
        }

        $c->delete();
        return response()->json(['data' => ['id' => $id], 'meta' => $this->statusCounts(Auth::id())]);
    }

    /**
     * Toggle ACTIVE ↔ PAUSED. The old controller flipped the
     * remote Facebook status first; here we update locally and
     * leave the Graph sync for the follow-up.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $c = MetaCampaign::query()->forCurrentWorkspace()->findOrFail($id);
        $next = $c->status === 'ACTIVE' ? 'PAUSED' : 'ACTIVE';

        $graph       = $this->graphFor($c);
        $metaEnabled = (bool) \App\Models\SystemSetting::get('meta_ads_enabled', false);

        // Activating an ad that was only saved LOCALLY (never pushed to Meta —
        // e.g. created before the Meta connection was ready) → publish the full
        // tree first. Without this, "activate" only flips a local flag and the
        // ad never appears in the customer's Ads Manager (the reported bug).
        if ($next === 'ACTIVE' && !$c->facebook_id && $c->status !== 'DRAFT'
            && $metaEnabled && $graph->isConfigured()) {
            $this->syncToMeta($c);
            $c->refresh();
        }

        // Flip the FULL CTWA tree on Meta (campaign + adset + ad).
        // Toggling just the campaign leaves the child ad ACTIVE which
        // Meta then bills against the now-paused campaign — broken
        // billing UX.
        if ($c->facebook_id || $c->meta_ad_id) {
            if ($graph->isConfigured()) {
                $graph->setStatusCascade($c, $next);
            }
        }
        $c->status = $next;
        $c->save();

        return response()->json([
            'data' => [
                'id'              => $c->id,
                'status'          => $c->status,
                'facebook_id'     => $c->facebook_id,
                'meta_last_error' => $c->meta_last_error,
            ],
            'meta' => $this->statusCounts(Auth::id()),
        ]);
    }

    /**
     * Fetch existing campaigns from the connected Meta ad account and upsert
     * them locally (matched by facebook_id) so ads created directly in Ads
     * Manager appear in WaDesk with their stats. Existing rows refresh; new
     * remote ads are imported. JSON for the button's AJAX, redirect for a
     * plain form submit.
     */
    public function importFromMeta(Request $request)
    {
        $graph = $this->graphForWorkspace();
        if (!$graph->isConfigured()) {
            $err = 'Connect your Meta ad account first (Ad Account ID + access token).';
            return ($request->wantsJson() || $request->ajax())
                ? response()->json(['ok' => false, 'error' => $err], 422)
                : back()->with('error', $err);
        }

        $remote = $graph->listCampaigns(100);
        \Illuminate\Support\Facades\Log::info('[META-IMPORT] importFromMeta', [
            'configured' => $graph->isConfigured(),
            'account'    => $graph->adAccountId(),
            'remote'     => count($remote),
        ]);
        $userId = Auth::id();
        $wsId   = (int) (Auth::user()?->current_workspace_id ?? 0);
        $imported = 0; $updated = 0;

        foreach ($remote as $r) {
            $fbId = (string) ($r['id'] ?? '');
            if ($fbId === '') continue;

            $eff         = strtoupper((string) ($r['effective_status'] ?? $r['status'] ?? ''));
            $local       = $eff === 'ACTIVE' ? 'ACTIVE' : 'PAUSED';
            $budgetMinor = (int) ($r['daily_budget'] ?? $r['lifetime_budget'] ?? 0);
            // Insights pulled per-campaign (the list call no longer nests them,
            // so an insights error can't blank the whole import).
            $ins         = $graph->fetchInsights($fbId) ?: [];

            $attrs = [
                'name'            => (string) ($r['name'] ?? 'Imported campaign'),
                'status'          => $local,
                'objective'       => $r['objective'] ?? null,
                'facebook_id'     => $fbId,
                'insights'        => is_array($ins) ? $ins : [],
                'meta_synced_at'  => now(),
                'meta_last_error' => null,
            ];
            if ($budgetMinor > 0) $attrs['daily_budget'] = round($budgetMinor / 100, 2);

            $existing = MetaCampaign::query()->forCurrentWorkspace()->where('facebook_id', $fbId)->first();
            if ($existing) {
                $existing->fill($attrs)->save();
                $updated++;
            } else {
                // Safe defaults first so a NOT-NULL column can't reject the
                // insert; $attrs overrides them, the ids override $attrs.
                MetaCampaign::create(array_merge(
                    ['optimization_goal' => 'MESSAGES', 'daily_budget' => 1],
                    $attrs,
                    ['user_id' => $userId, 'workspace_id' => $wsId ?: null],
                ));
                $imported++;
            }
        }

        // Surface Meta's real reason (e.g. the "ads_read not granted" 403) inline
        // instead of a vague "no ads" — only when nothing came back.
        $err = (!$imported && !$updated) ? $graph->lastListError : null;
        $msg = ($imported || $updated)
            ? "Fetched from Meta: {$imported} new, {$updated} updated."
            : ($err ? ('Meta: ' . $err) : 'No campaigns found in your Meta ad account.');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => !$err, 'imported' => $imported, 'updated' => $updated,
                'total' => count($remote), 'message' => $msg, 'error' => $err,
                'meta' => $this->statusCounts($userId),
            ], $err ? 422 : 200);
        }
        return $err ? back()->with('error', $msg) : back()->with('status', $msg);
    }

    /**
     * Refresh insights placeholder — currently regenerates the
     * sample metrics deterministically. Real implementation hits
     * the Graph API and replaces the `insights` JSON column.
     */
    public function sync(): JsonResponse
    {
        $userId = Auth::id();
        $wsId   = (int) (\Illuminate\Support\Facades\Auth::user()?->current_workspace_id ?? 0);

        // Resolve the workspace's Meta config once for all campaigns in
        // this batch — avoids spinning up a new client per row.
        $graph     = $this->graphForWorkspace();
        $useGraph  = $graph->isConfigured();

        $touched = 0;
        MetaCampaign::query()->forCurrentWorkspace()->each(function (MetaCampaign $c) use ($graph, $useGraph, &$touched) {
            // Real Graph insights when configured; fall back to
            // deterministic placeholder numbers otherwise so the
            // dashboard isn't blank without credentials.
            $insights = $useGraph && $c->facebook_id
                ? $graph->fetchInsights($c->facebook_id)
                : [];
            if (empty($insights)) {
                $insights = $this->fakeInsightsFor($c);
            }
            $c->insights       = $insights;
            $c->meta_synced_at = now();
            $c->save();
            $touched++;
        });

        return response()->json([
            'data'      => $this->totals($userId),
            'source'    => $useGraph ? 'meta-graph' : 'placeholder',
            'touched'   => $touched,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function validateCampaign(Request $request, bool $updating = false): array
    {
        $rule = $updating ? 'sometimes' : 'required';

        // The CREATE form posts interests as a multi-select array (interests[]);
        // the EDIT form posts a comma/newline TEXTAREA (a single string). Without
        // normalizing, the array rule below 422s every edit with "interests must
        // be an array". Split a string into a trimmed array so both forms pass.
        if ($request->has('interests') && is_string($request->input('interests'))) {
            $raw = preg_split('/[\n,]+/', (string) $request->input('interests')) ?: [];
            $request->merge([
                'interests' => array_values(array_filter(array_map('trim', $raw), fn ($s) => $s !== '')),
            ]);
        }

        return $request->validate([
            'name'                => "{$rule}|string|max:255",
            'optimization_goal'   => "{$rule}|in:LINK_CLICKS,CONVERSIONS,LEAD_GENERATION,MESSAGES,REACH,BRAND_AWARENESS,VIDEO_VIEWS",
            'daily_budget'        => "{$rule}|numeric|min:1",
            'adset_name'          => "sometimes|string|max:255",
            'creative_title'      => 'sometimes|nullable|string|max:255',
            'creative_body'       => 'sometimes|nullable|string|max:4096',
            'creative_link_url'   => 'sometimes|nullable|url|max:1024',
            'creative_image_file' => 'sometimes|nullable|image|max:10240',
            // target_countries arrives as an array of ISO codes from the
            // chip picker (e.g. ["IN","AE"]). Legacy free-text submits
            // still accepted as a comma-separated string for backwards
            // compat with any saved-form drafts.
            'target_countries'    => 'sometimes|nullable',
            'target_countries.*'  => 'sometimes|string|size:2',
            'age_min'             => 'sometimes|nullable|integer|min:13|max:80',
            'age_max'             => 'sometimes|nullable|integer|min:13|max:80',
            'gender'              => 'sometimes|nullable|in:male,female,all',
            // interests arrives as an array of NAMES from the curated
            // dropdown — MetaGraphClient resolves them to Meta IDs via
            // Targeting Search at sync time.
            'interests'           => 'sometimes|nullable|array',
            'interests.*'         => 'sometimes|string|max:120',
            'ctwa_enabled'        => 'sometimes|boolean',
            'ctwa_phone'          => 'sometimes|nullable|string|max:32',
            'ctwa_message'        => 'sometimes|nullable|string|max:500',
            'ctwa_cta'            => 'sometimes|nullable|in:WHATSAPP_MESSAGE,LEARN_MORE,SHOP_NOW,SIGN_UP,BOOK_TRAVEL,GET_QUOTE,CONTACT_US',
            // Instagram Ads. ad_type drives the destination/creative shape;
            // publisher_platforms + instagram_positions drive placement.
            'ad_type'                => 'sometimes|nullable|in:ctwa,link,ig_direct',
            'placement_edited'       => 'sometimes|nullable',
            'publisher_platforms'    => 'sometimes|nullable|array',
            'publisher_platforms.*'  => 'sometimes|string|in:facebook,instagram',
            'instagram_positions'    => 'sometimes|nullable|array',
            'instagram_positions.*'  => 'sometimes|string|in:stream,story,reels',
            // Initial status the user picks on the form. Restricted to
            // the values the front-end exposes — PAUSED keeps the ad
            // dormant on Meta (default), ACTIVE flips it live right
            // after sync, DRAFT keeps it local-only.
            'status'              => 'sometimes|nullable|in:ACTIVE,PAUSED,DRAFT',
        ]);
    }

    /**
     * Reshape the validated form into MetaCampaign column names —
     * mostly identity, but assembles the `targeting` array out of
     * the flat country/age/gender/interest fields and infers the
     * Meta `objective` from the optimization goal (mirrors the
     * mapping the old CampaignController had).
     */
    private function mapValidatedToColumns(array $data): array
    {
        $out = [
            'name'              => $data['name']              ?? null,
            'optimization_goal' => $data['optimization_goal'] ?? null,
            'daily_budget'      => $data['daily_budget']      ?? null,
            'creative_title'    => $data['creative_title']    ?? null,
            'creative_body'     => $data['creative_body']     ?? null,
            'creative_link_url' => $data['creative_link_url'] ?? null,
            'ctwa_enabled'      => (bool) ($data['ctwa_enabled'] ?? false),
            'ctwa_phone'        => $data['ctwa_phone']        ?? null,
            'ctwa_message'      => $data['ctwa_message']      ?? null,
            'ctwa_cta'          => $data['ctwa_cta']          ?? null,
            // Honour the user's status choice — pre-2026-05-24 this was
            // silently dropped by the validator and `store()` always
            // hard-coded PAUSED. ACTIVE → push live after sync; PAUSED
            // → live on Meta but dormant; DRAFT → local-only, skip
            // Meta sync entirely (used by users still drafting).
            'status'            => $data['status']            ?? null,
        ];
        $out = array_filter($out, fn ($v) => $v !== null);

        // ad_type — defaults to ctwa (legacy + Click-to-WhatsApp).
        $adType = strtolower((string) ($data['ad_type'] ?? 'ctwa'));
        if (! in_array($adType, \App\Models\MetaCampaign::AD_TYPES, true)) {
            $adType = \App\Models\MetaCampaign::AD_TYPE_CTWA;
        }
        $out['ad_type'] = $adType;

        // Objective by ad type. Messaging ads (CTWA + Instagram-Direct)
        // → ENGAGEMENT (required for WHATSAPP / INSTAGRAM_DIRECT
        // destinations). Plain link ads → traffic, or awareness when the
        // user picked a reach/awareness goal.
        if (in_array($adType, [\App\Models\MetaCampaign::AD_TYPE_CTWA, \App\Models\MetaCampaign::AD_TYPE_IG_DIRECT], true)) {
            $out['objective'] = 'OUTCOME_ENGAGEMENT';
        } elseif (isset($data['optimization_goal'])) {
            $goal = strtoupper((string) $data['optimization_goal']);
            $out['objective'] = in_array($goal, ['REACH', 'BRAND_AWARENESS'], true)
                ? 'OUTCOME_AWARENESS'
                : 'OUTCOME_TRAFFIC';
        }

        // Placement — publisher_platforms (facebook/instagram) +
        // instagram_positions (only meaningful with instagram). Empty →
        // null = Advantage+ automatic placements (legacy behaviour).
        //
        // The placement UI posts a hidden `placement_edited=1` so we can tell
        // "user cleared every checkbox to go automatic" (no array key sent at
        // all) apart from "this form had no placement section". Without the
        // sentinel, unchecking all boxes on edit would leave the old value.
        $placementEdited = ! empty($data['placement_edited']);
        if ($placementEdited || array_key_exists('publisher_platforms', $data)) {
            $pp = array_values(array_intersect(
                array_map('strtolower', (array) ($data['publisher_platforms'] ?? [])),
                ['facebook', 'instagram']
            ));
            $out['publisher_platforms'] = ! empty($pp) ? $pp : null;
        }
        if ($placementEdited || array_key_exists('instagram_positions', $data)) {
            $ip = array_values(array_intersect(
                array_map('strtolower', (array) ($data['instagram_positions'] ?? [])),
                ['stream', 'story', 'reels']
            ));
            $out['instagram_positions'] = ! empty($ip) ? $ip : null;
        }

        // target_countries is now an array of ISO codes from the chip
        // picker. Older drafts may still post a comma-separated string —
        // handle both so saved-form-restore doesn't break.
        $rawCountries = $data['target_countries'] ?? null;
        $countries    = null;
        if (is_array($rawCountries)) {
            $countries = array_values(array_filter(array_map(
                fn ($x) => strtoupper(trim((string) $x)),
                $rawCountries
            )));
        } elseif (is_string($rawCountries) && $rawCountries !== '') {
            $countries = array_values(array_filter(array_map(
                fn ($x) => strtoupper(trim($x)),
                explode(',', $rawCountries)
            )));
        }

        // interests arrives as a curated-catalog name array. The graph
        // client resolves names to Meta IDs via Targeting Search at
        // sync time, so all we do here is normalise + dedupe.
        $rawInterests = $data['interests'] ?? null;
        $interests    = null;
        if (is_array($rawInterests)) {
            $interests = array_values(array_unique(array_filter(array_map(
                fn ($x) => trim((string) $x),
                $rawInterests
            ))));
        }

        $targeting = array_filter([
            'countries' => $countries,
            'age_min'   => $data['age_min'] ?? null,
            'age_max'   => $data['age_max'] ?? null,
            'gender'    => $data['gender']  ?? null,
            'interests' => $interests,
            // Stored alongside targeting because there's no dedicated
            // column for it. Underscore prefix marks it as local
            // metadata — buildTargeting() drops underscore keys before
            // shipping the rest to Meta as a real targeting spec.
            '_adset_name' => $data['adset_name'] ?? null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        if (!empty($targeting)) $out['targeting'] = $targeting;
        return $out;
    }

    /**
     * The same goal → objective table the old project used.
     */
    private function objectiveFor(string $goal): string
    {
        return match ($goal) {
            'LINK_CLICKS'     => 'OUTCOME_TRAFFIC',
            'CONVERSIONS'     => 'OUTCOME_SALES',
            'LEAD_GENERATION' => 'OUTCOME_LEADS',
            'MESSAGES'        => 'OUTCOME_ENGAGEMENT',
            'REACH'           => 'REACH',
            'BRAND_AWARENESS' => 'OUTCOME_AWARENESS',
            'VIDEO_VIEWS'     => 'OUTCOME_ENGAGEMENT',
            default           => 'OUTCOME_TRAFFIC',
        };
    }

    private function applySort($items, string $sort)
    {
        return match ($sort) {
            'date-asc'   => $items->sortBy('created_at')->values(),
            'name-asc'   => $items->sortBy(fn ($c)     => mb_strtolower((string) $c->name))->values(),
            'name-desc'  => $items->sortByDesc(fn ($c) => mb_strtolower((string) $c->name))->values(),
            'spend-desc' => $items->sortByDesc(fn ($c) => $c->metrics['spend'])->values(),
            default      => $items->sortByDesc('created_at')->values(),
        };
    }

    private function statusCounts(?int $userId): array
    {
        $rows = MetaCampaign::query()->forCurrentWorkspace()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'all'       => MetaCampaign::query()->forCurrentWorkspace()->count(),
            'ACTIVE'    => (int) ($rows['ACTIVE']    ?? 0),
            'PAUSED'    => (int) ($rows['PAUSED']    ?? 0),
            'SCHEDULED' => (int) ($rows['SCHEDULED'] ?? 0),
            'DRAFT'     => (int) ($rows['DRAFT']     ?? 0),
            'FAILED'    => (int) ($rows['FAILED']    ?? 0),
        ];
    }

    private function objectiveCounts(?int $userId): array
    {
        $rows = MetaCampaign::query()->forCurrentWorkspace()
            ->selectRaw('optimization_goal, COUNT(*) as c')
            ->groupBy('optimization_goal')
            ->pluck('c', 'optimization_goal');

        return collect(MetaCampaign::OPTIMIZATION_GOALS)
            ->mapWithKeys(fn ($g) => [$g => (int) ($rows[$g] ?? 0)])
            ->all();
    }

    /**
     * Aggregate metrics for the stat row across the user's
     * non-deleted campaigns. `insights` is plain JSON (not
     * encrypted — performance metrics aren't PII) so a quick
     * in-PHP reduce is cheap.
     */
    private function totals(?int $userId): array
    {
        $items = MetaCampaign::query()->forCurrentWorkspace()->get(['status', 'insights']);
        $sumSpend  = $items->sum(fn ($c) => (float) ($c->insights['spend']  ?? 0));
        $sumClicks = $items->sum(fn ($c) => (int)   ($c->insights['clicks'] ?? 0));
        return [
            'total'        => $items->count(),
            'active'       => $items->where('status', 'ACTIVE')->count(),
            'spend'        => round($sumSpend, 2),
            'clicks'       => $sumClicks,
        ];
    }

    /**
     * Deterministic placeholder metrics so the dashboard isn't
     * blank pre-Graph-sync. Hash on the campaign id so a given
     * row's numbers stay stable across page refreshes.
     */
    private function fakeInsightsFor(MetaCampaign $c): array
    {
        $seed = crc32($c->id . '|' . $c->name);
        mt_srand($seed);
        $impressions = mt_rand(20_000, 90_000);
        $clicks      = (int) round($impressions * mt_rand(80, 350) / 10_000);
        $spend       = round($clicks * mt_rand(15, 35) / 100, 2);
        $reach       = (int) round($impressions * mt_rand(60, 80) / 100);
        $conv        = (int) round($clicks * mt_rand(3, 15) / 100);
        $revenue     = round($conv * mt_rand(800, 2200) / 100, 2);
        mt_srand();
        return [
            'spend'       => $spend,
            'impressions' => $impressions,
            'clicks'      => $clicks,
            'reach'       => $reach,
            'conversions' => $conv,
            'ctr'         => $impressions ? round($clicks / $impressions * 100, 2) : 0,
            'cpc'         => $clicks      ? round($spend / $clicks, 2)             : 0,
            'revenue'     => $revenue,
        ];
    }

    // -----------------------------------------------------------------
    // Build-with-AI
    // -----------------------------------------------------------------

    /**
     * GET /meta-ads/api/ai-models — list admin-enabled text-generation
     * providers/models. Mirrors TemplatesController::apiAiModels and
     * FlowsController::apiAiModels so the UI picker stays consistent.
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
     * POST /meta-ads/api/ai-generate — generate a ready-to-fill ad
     * brief (campaign + adset names, headline, body, audience hints,
     * CTWA message) from a structured input plus optional free-form
     * prompt. The front-end pastes the response into the existing
     * #campaignForm inputs.
     */
    public function apiAiGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model'              => 'required|string|max:120',
            'provider'           => 'required|string|in:openai,anthropic,gemini',
            'business_name'      => 'required|string|max:191',
            'product'            => 'nullable|string|max:255',
            'objective'          => 'nullable|string|max:60',
            'audience'           => 'nullable|string|max:500',
            'countries'          => 'nullable|string|max:255',
            'destination_url'    => 'nullable|string|max:1024',
            'cta'                => 'nullable|string|max:60',
            'tone'               => 'nullable|string|max:60',
            'whatsapp_message'   => 'nullable|boolean',
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
You write Meta (Facebook + Instagram) advertising copy. Output STRICT
JSON only — no prose, no markdown, no code fences. Schema:

{
  "campaign_name":      "<short, max 60>",
  "adset_name":         "<short, max 60>",
  "headline":           "<max 40 chars, attention-grabbing, no clickbait>",
  "body":               "<primary text, max 280 chars, persuasive, no emoji>",
  "interests":          "<comma-separated targeting interests, max 5 phrases>",
  "ctwa_message":       "<message the user sends when they tap WhatsApp CTA, max 200, optional>",
  "suggested_age_min":  <integer 13-65>,
  "suggested_age_max":  <integer 13-65>,
  "suggested_countries":"<comma-separated ISO 3166-1 alpha-2 codes, e.g. US,GB,IN>"
}

Rules:
1. Follow Meta's ad policy — no banned health/finance claims, no
   discriminatory language, no exaggerated promises.
2. No emojis. Plain text only. Capitalise correctly (no ALL CAPS).
3. Headline must hook in under 40 characters.
4. Body should describe the offer + a clear next step.
5. interests is comma-separated; the operator's targeting box accepts
   the raw string.
6. Pick suggested_countries as 1-5 ISO codes inferred from the brief.
7. Output ONLY the JSON object. No explanation. No code fences.
SYS;

        // Assemble the brief.
        $lines = [];
        $lines[] = 'Business name: ' . $data['business_name'];
        if (!empty($data['product']))         $lines[] = 'Product / service: ' . $data['product'];
        if (!empty($data['objective']))       $lines[] = 'Campaign objective: ' . $data['objective'];
        if (!empty($data['audience']))        $lines[] = 'Target audience: ' . $data['audience'];
        if (!empty($data['countries']))       $lines[] = 'Markets: ' . $data['countries'];
        if (!empty($data['destination_url'])) $lines[] = 'Destination URL: ' . $data['destination_url'];
        if (!empty($data['cta']))             $lines[] = 'Preferred CTA: ' . $data['cta'];
        if (!empty($data['tone']))            $lines[] = 'Tone: ' . $data['tone'];
        if (!empty($data['whatsapp_message'])) {
            $lines[] = 'Include a CTWA (Click-to-WhatsApp) message — the customer taps the ad and lands in WhatsApp pre-filled with the message.';
        }
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
            Log::warning('[AI-MetaAds] bad JSON from model: ' . substr($raw, 0, 400));
            return response()->json([
                'ok'      => false,
                'error'   => 'bad_json',
                'message' => 'Model output was not valid JSON. Try again or refine the brief.',
                'raw'     => mb_substr($raw, 0, 600),
            ], 422);
        }

        // Hard caps — match the validateCampaign() rules so the
        // front-end never paints something the controller will reject
        // at submit time.
        $payload = [
            'campaign_name'       => mb_substr((string) ($tpl['campaign_name'] ?? ''), 0, 255),
            'adset_name'          => mb_substr((string) ($tpl['adset_name'] ?? ''), 0, 255),
            'headline'            => mb_substr((string) ($tpl['headline'] ?? ''), 0, 255),
            'body'                => mb_substr((string) ($tpl['body'] ?? ''), 0, 4096),
            'interests'           => mb_substr((string) ($tpl['interests'] ?? ''), 0, 2048),
            'ctwa_message'        => mb_substr((string) ($tpl['ctwa_message'] ?? ''), 0, 500),
            'suggested_age_min'   => is_numeric($tpl['suggested_age_min'] ?? null) ? max(13, min(65, (int) $tpl['suggested_age_min'])) : null,
            'suggested_age_max'   => is_numeric($tpl['suggested_age_max'] ?? null) ? max(13, min(65, (int) $tpl['suggested_age_max'])) : null,
            'suggested_countries' => mb_substr((string) ($tpl['suggested_countries'] ?? ''), 0, 255),
        ];

        return response()->json([
            'ok'    => true,
            'ad'    => $payload,
            'model' => $data['model'],
        ]);
    }
}
