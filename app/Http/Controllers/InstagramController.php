<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramService;
use App\Models\InstagramAutomation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * User-facing Instagram automation pages — the "Instaflow" suite.
 *   /instagram             → dashboard (accounts + automations + KPIs)
 *   /instagram/automations → build keyword→DM + comment→DM rules
 */
class InstagramController extends Controller
{
    private function wsId(): int
    {
        return (int) (Auth::user()?->current_workspace_id ?? 0);
    }

    public function dashboard()
    {
        $wsId = $this->wsId();
        $accounts    = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();
        $automations = InstagramAutomation::where('workspace_id', $wsId)->orderByDesc('id')->get();

        $stats = [
            'accounts'    => $accounts->count(),
            'live'        => $accounts->where('status', 'connected')->count(),
            'automations' => $automations->where('is_active', true)->count(),
            'fired'       => (int) $automations->sum('fired_count'),
        ];

        // No-cron policy: drain pending bulk DMs AFTER the response, gated to
        // once / 30s / workspace so a dashboard view never blocks or hammers.
        if (\Illuminate\Support\Facades\Cache::add('ig_bcast_sweep_' . $wsId, 1, 30)) {
            app()->terminating(function () {
                try { \Illuminate\Support\Facades\Artisan::call('instagram:run-broadcasts', ['--batch' => 15]); }
                catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('[IG-BCAST] sweep failed: ' . $e->getMessage()); }
                try { \Illuminate\Support\Facades\Artisan::call('instagram:run-scheduled'); }
                catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('[IG-SCHED] sweep failed: ' . $e->getMessage()); }
            });
        }
        // Keep long-lived tokens alive: refresh nearing-expiry tokens once/day/workspace.
        if (\Illuminate\Support\Facades\Cache::add('ig_token_sweep_' . $wsId, 1, 86400)) {
            app()->terminating(function () use ($wsId) {
                try { \Illuminate\Support\Facades\Artisan::call('instagram:refresh-tokens', ['--workspace' => $wsId]); }
                catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('[IG-TOKEN] sweep failed: ' . $e->getMessage()); }
            });
        }

        return view('instagram.dashboard', compact('accounts', 'automations', 'stats'));
    }

    /** Content calendar — scheduled IG posts / reels / stories (month grid). */
    public function calendar(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();

        $month = \Illuminate\Support\Carbon::parse(($request->query('month') ?: now()->format('Y-m')) . '-01')->startOfMonth();
        $gridStart = $month->copy()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);

        $posts = \App\Models\InstagramScheduledPost::where('workspace_id', $wsId)
            ->whereBetween('scheduled_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
            ->orderBy('scheduled_at')->get();

        return view('instagram.calendar', compact('accounts', 'posts', 'month', 'gridStart', 'gridEnd'));
    }

    /** Comment automations — comment-keyword → DM rules. */
    public function autoComments()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();
        $rules = InstagramAutomation::where('workspace_id', $wsId)
            ->where('type', 'comment_to_dm')->orderByDesc('id')->get();
        return view('instagram.auto-comments', compact('accounts', 'rules'));
    }

    /** Instagram ads — promote posts / story ads via the Meta Marketing API. */
    public function ads()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();
        // Recent posts for the "boost an existing post" picker.
        $boostMedia = [];
        if ($acc = $accounts->where('status', 'connected')->first()) {
            $boostMedia = \Illuminate\Support\Facades\Cache::remember("ig_media_{$acc->id}", 600, fn () => (new InstagramService($acc))->getMedia(12));
        }
        return view('instagram.ads', compact('accounts', 'boostMedia'));
    }

    /** Boost an existing IG post — builds a paused engagement ad via the Marketing API. */
    public function boostPost(Request $request)
    {
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'media_id'             => 'required|string|max:64',
            'daily_budget'         => 'required|numeric|min:1',
            'days'                 => 'required|integer|min:1|max:30',
        ]);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);

        $cfg = \App\Models\WaProviderConfig::query()->where('workspace_id', $this->wsId())
            ->whereIn('provider', ['meta_ads', 'waba'])
            ->orderByRaw("CASE provider WHEN 'meta_ads' THEN 0 ELSE 1 END")
            ->orderByDesc('is_primary')->orderByDesc('id')->first();
        $graph = new \App\Services\MetaGraphClient($cfg);
        if (!$graph->isConfigured()) {
            return back()->withErrors(['instagram' => 'Connect a Meta ad account first at /meta-ads.']);
        }
        if ($acc->ig_user_id) $graph->withInstagramUserId((string) $acc->ig_user_id);

        $res = $graph->boostInstagramMedia($d['media_id'], (float) $d['daily_budget'], (int) $d['days']);
        if (!empty($res['ok'])) {
            return back()->with('status', 'Boost created (paused) — review + activate it in Ads Manager. Ad ID ' . ($res['ad_id'] ?? '') . '.');
        }
        return back()->withErrors(['instagram' => 'Boost failed: ' . ($res['error'] ?? 'unknown')]);
    }

    /** Resolve a workspace-owned connected account from a request field. */
    private function igAccount(Request $request, string $field = 'instagram_account_id'): ?InstagramAccount
    {
        return InstagramAccount::forWorkspace($this->wsId())->where('id', (int) $request->input($field))->first();
    }

    // ───────────── Comment moderation ─────────────

    /** Live comment moderation — pick a post, see + act on its comments. */
    public function comments(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $account  = $accounts->firstWhere('id', (int) $request->query('account')) ?: $accounts->first();
        $media = $account ? (new InstagramService($account))->getMedia(24) : [];
        $selectedMediaId = (string) $request->query('media', '');
        $comments = ($account && $selectedMediaId !== '')
            ? (new InstagramService($account))->getComments($selectedMediaId, 50)
            : [];
        return view('instagram.comments', compact('accounts', 'account', 'media', 'selectedMediaId', 'comments'));
    }

    public function commentReply(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'comment_id' => 'required|string|max:64', 'message' => 'required|string|max:2000']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->replyComment($d['comment_id'], $d['message']);
        return $this->igResult($r, 'Reply posted.');
    }

    public function commentPrivateReply(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'comment_id' => 'required|string|max:64', 'message' => 'required|string|max:1000']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->privateReply($d['comment_id'], $d['message']);
        return $this->igResult($r, 'Private DM sent.');
    }

    public function commentCreate(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'media_id' => 'required|string|max:64', 'message' => 'required|string|max:2000']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->commentOnMedia($d['media_id'], $d['message']);
        return $this->igResult($r, 'Comment posted.');
    }

    public function commentHide(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'comment_id' => 'required|string|max:64', 'hide' => 'nullable|boolean']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->hideComment($d['comment_id'], (bool) ($d['hide'] ?? true));
        return $this->igResult($r, ($d['hide'] ?? true) ? 'Comment hidden.' : 'Comment shown.');
    }

    public function commentDelete(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'comment_id' => 'required|string|max:64']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->deleteComment($d['comment_id']);
        return $this->igResult($r, 'Comment deleted.');
    }

    // ───────────── Discovery (competitor + hashtag) ─────────────

    public function discovery(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        return view('instagram.discovery', compact('accounts'));
    }

    public function discoverySearch(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'username' => 'required|string|max:60']);
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $profile = (new InstagramService($acc))->businessDiscovery($d['username']);
        if (empty($profile)) {
            return back()->withErrors(['instagram' => 'No public Business/Creator account found for @' . ltrim($d['username'], '@') . ' (needs a Facebook-login account with insights access).'])->withInput();
        }
        return view('instagram.discovery', compact('accounts', 'profile'))->with('queried', $d['username']);
    }

    public function hashtagSearch(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'hashtag' => 'required|string|max:100', 'kind' => 'nullable|in:top_media,recent_media']);
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $svc = new InstagramService($acc);
        $id = $svc->hashtagId($d['hashtag']);
        if ($id === '') {
            return back()->withErrors(['instagram' => 'Hashtag not found, or the weekly 30-hashtag limit is reached.'])->withInput();
        }
        $hashtagMedia = $svc->hashtagMedia($id, $d['kind'] ?? 'top_media', 24);
        return view('instagram.discovery', compact('accounts', 'hashtagMedia'))
            ->with('queriedTag', ltrim($d['hashtag'], '#'))->with('hashtagKind', $d['kind'] ?? 'top_media');
    }

    // ───────────── Per-post analytics ─────────────

    /** JSON insights for one media (analytics drill-down). */
    public function postInsights(Request $request, string $mediaId)
    {
        if (!$acc = $this->igAccount($request, 'account')) return response()->json(['error' => 'account not found'], 404);
        $data = (new InstagramService($acc))->mediaInsights($mediaId, (string) $request->query('type', ''));
        return response()->json(['ok' => true, 'metrics' => $data]);
    }

    // ───────────── Scheduled-post edit / delete ─────────────

    public function scheduledUpdate(Request $request, int $id)
    {
        $d = $request->validate(['schedule_at' => 'required|date|after:now', 'caption' => 'nullable|string|max:2200']);
        $post = \App\Models\InstagramScheduledPost::where('workspace_id', $this->wsId())->where('id', $id)->where('status', 'pending')->first();
        if (!$post) return back()->withErrors(['instagram' => 'Only a pending scheduled post can be edited.']);
        $post->scheduled_at = $d['schedule_at'];
        if (array_key_exists('caption', $d)) $post->caption = $d['caption'];
        $post->save();
        return back()->with('status', 'Scheduled post updated.');
    }

    public function scheduledDestroy(int $id)
    {
        $post = \App\Models\InstagramScheduledPost::where('workspace_id', $this->wsId())->where('id', $id)->where('status', 'pending')->first();
        if (!$post) return back()->withErrors(['instagram' => 'Only a pending scheduled post can be removed.']);
        $post->delete();
        return back()->with('status', 'Scheduled post removed.');
    }

    /** Flash a Graph-call result (['ok'=>bool,'error'?]) to the session. */
    private function igResult(array $r, string $okMsg)
    {
        return ($r['ok'] ?? false)
            ? back()->with('status', $okMsg)
            : back()->withErrors(['instagram' => (string) ($r['error'] ?? 'Action failed.')]);
    }

    public function automations()
    {
        $wsId = $this->wsId();
        $accounts    = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $automations = InstagramAutomation::where('workspace_id', $wsId)->orderByDesc('id')->get();
        // AI-Training assistants for the AI-agent knowledge-base picker.
        $assistants  = \App\Models\AiChatAssistant::where('workspace_id', $wsId)->orderBy('name')->get(['id', 'name']);
        // Visual Instagram flows (flow_type=instagram) for the "Run a flow" picker.
        $igFlows     = \App\Models\Flow::where('workspace_id', $wsId)->where('flow_type', 'instagram')->orderByDesc('id')->get();
        return view('instagram.automations', compact('accounts', 'automations', 'assistants', 'igFlows'));
    }

    public function automationStore(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'type'                 => 'required|in:dm_keyword,comment_to_dm,ai_agent,flow,story_reply,mention',
            'name'                 => 'nullable|string|max:191',
            'trigger_keyword'      => 'nullable|string|max:500',
            'match_mode'           => 'nullable|in:contains,exact,any',
            'post_id'              => 'nullable|string|max:64',
            'public_reply'         => 'nullable|string|max:500',
            'dm_message'           => 'required_unless:type,flow,mention|nullable|string|max:2000',
            'ai_assistant_id'      => 'nullable|integer',
            'ai_model'             => 'nullable|string|max:120',
            'flow_id'              => 'nullable|integer',
        ]);

        // Flow automations must point at a flow_type=instagram flow in this workspace.
        if ($data['type'] === 'flow') {
            $ok = \App\Models\Flow::where('workspace_id', $wsId)->where('flow_type', 'instagram')->where('id', (int) ($data['flow_id'] ?? 0))->exists();
            if (!$ok) return back()->withErrors(['instagram' => 'Pick an Instagram flow to run.']);
        }

        // Ownership check — the account must belong to this workspace.
        $owns = InstagramAccount::forWorkspace($wsId)->where('id', $data['instagram_account_id'])->exists();
        if (!$owns) return back()->withErrors(['instagram' => 'That Instagram account is not in your workspace.']);

        // AI agent → stash model + knowledge-base assistant in meta_json.
        $meta = null;
        if ($data['type'] === 'ai_agent') {
            $meta = array_filter([
                'assistant_id' => (int) ($data['ai_assistant_id'] ?? 0) ?: null,
                'model'        => $data['ai_model'] ?? null,
            ]);
        }

        InstagramAutomation::create([
            'workspace_id'         => $wsId,
            'instagram_account_id' => (int) $data['instagram_account_id'],
            'type'                 => $data['type'],
            'name'                 => $data['name'] ?? null,
            'trigger_keyword'      => $data['trigger_keyword'] ?? null,
            'match_mode'           => $data['type'] === 'ai_agent' ? 'any' : ($data['match_mode'] ?? 'contains'),
            'post_id'              => $data['post_id'] ?? null,
            'public_reply'         => $data['public_reply'] ?? null,
            'dm_message'           => $data['dm_message'] ?? '',
            'flow_id'              => $data['type'] === 'flow' ? (int) ($data['flow_id'] ?? 0) : null,
            'is_active'            => true,
            'meta_json'            => $meta,
        ]);
        return back()->with('status', 'Automation created.');
    }

    /** Update an existing automation (full CRUD edit). Same fields as store. */
    public function automationUpdate(Request $request, int $id)
    {
        $wsId = $this->wsId();
        $a = InstagramAutomation::where('workspace_id', $wsId)->where('id', $id)->firstOrFail();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'type'                 => 'required|in:dm_keyword,comment_to_dm,ai_agent,flow,story_reply,mention',
            'name'                 => 'nullable|string|max:191',
            'trigger_keyword'      => 'nullable|string|max:500',
            'match_mode'           => 'nullable|in:contains,exact,any',
            'post_id'              => 'nullable|string|max:64',
            'public_reply'         => 'nullable|string|max:500',
            'dm_message'           => 'required_unless:type,flow,mention|nullable|string|max:2000',
            'ai_assistant_id'      => 'nullable|integer',
            'ai_model'             => 'nullable|string|max:120',
            'flow_id'              => 'nullable|integer',
        ]);
        if ($data['type'] === 'flow') {
            $ok = \App\Models\Flow::where('workspace_id', $wsId)->where('flow_type', 'instagram')->where('id', (int) ($data['flow_id'] ?? 0))->exists();
            if (!$ok) return back()->withErrors(['instagram' => 'Pick an Instagram flow to run.']);
        }
        if (!InstagramAccount::forWorkspace($wsId)->where('id', $data['instagram_account_id'])->exists()) {
            return back()->withErrors(['instagram' => 'That Instagram account is not in your workspace.']);
        }
        $meta = $data['type'] === 'ai_agent'
            ? array_filter(['assistant_id' => (int) ($data['ai_assistant_id'] ?? 0) ?: null, 'model' => $data['ai_model'] ?? null])
            : null;
        $a->update([
            'instagram_account_id' => (int) $data['instagram_account_id'],
            'type'                 => $data['type'],
            'name'                 => $data['name'] ?? null,
            'trigger_keyword'      => $data['trigger_keyword'] ?? null,
            'match_mode'           => $data['type'] === 'ai_agent' ? 'any' : ($data['match_mode'] ?? 'contains'),
            'post_id'              => $data['post_id'] ?? null,
            'public_reply'         => $data['public_reply'] ?? null,
            'dm_message'           => $data['dm_message'] ?? '',
            'flow_id'              => $data['type'] === 'flow' ? (int) ($data['flow_id'] ?? 0) : null,
            'meta_json'            => $meta,
        ]);
        return back()->with('status', 'Automation updated.');
    }

    /** Pickers shared by the create + edit automation wizards. */
    private function automationFormData(): array
    {
        $wsId = $this->wsId();
        return [
            'accounts'   => InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get(),
            'assistants' => \App\Models\AiChatAssistant::where('workspace_id', $wsId)->orderBy('name')->get(['id', 'name']),
            'igFlows'    => \App\Models\Flow::where('workspace_id', $wsId)->where('flow_type', 'instagram')->orderByDesc('id')->get(),
        ];
    }

    /** Multi-step CREATE wizard page (separate page, auto-reply style). */
    public function automationsCreate()
    {
        return view('instagram.automations.create', array_merge($this->automationFormData(), ['automation' => null]));
    }

    /** Multi-step EDIT wizard page — same wizard, pre-filled. */
    public function automationEdit(int $id)
    {
        $automation = InstagramAutomation::where('workspace_id', $this->wsId())->where('id', $id)->firstOrFail();
        return view('instagram.automations.edit', array_merge($this->automationFormData(), ['automation' => $automation]));
    }

    /** Automations analytics page — fired volume, per-type + per-rule breakdown. */
    public function automationsAnalytics()
    {
        $wsId = $this->wsId();
        $automations = InstagramAutomation::where('workspace_id', $wsId)->get();
        $totalFired  = (int) $automations->sum('fired_count');
        $activeCount = $automations->where('is_active', true)->count();
        $byType = $automations->groupBy('type')->map(fn ($g) => [
            'count' => $g->count(),
            'fired' => (int) $g->sum('fired_count'),
            'active' => $g->where('is_active', true)->count(),
        ]);
        $top = $automations->sortByDesc('fired_count')->take(10)->values();
        return view('instagram.automations.analytics', compact('automations', 'totalFired', 'activeCount', 'byType', 'top'));
    }

    /** DM inbox — thread list + (optionally) one open thread. */
    public function inbox(Request $request)
    {
        $wsId     = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();
        $accIds   = $accounts->pluck('id')->all();

        // Latest message per (account, igsid) thread. The rail's Received /
        // Auto-sent links pass ?status=in|out — honour it (was ignored, so the
        // filters did nothing). 'All' (no status) shows every thread.
        $threads = collect();
        if ($accIds) {
            $status = (string) $request->query('status', '');
            $threads = \App\Models\InstagramMessage::whereIn('instagram_account_id', $accIds)
                ->when(in_array($status, ['in', 'out'], true), fn ($q) => $q->where('direction', $status))
                ->orderByDesc('id')->limit(400)->get()
                ->groupBy(fn ($m) => $m->instagram_account_id . ':' . $m->igsid)
                ->map(fn ($msgs) => $msgs->first())   // first = newest (desc order)
                ->values()->sortByDesc('id')->values();
        }

        // Open thread.
        $openKey  = (string) $request->query('thread', '');
        $messages = collect();
        $openIgsid = ''; $openAccountId = 0;
        if ($openKey && str_contains($openKey, ':')) {
            [$openAccountId, $openIgsid] = array_map('strval', explode(':', $openKey, 2));
            $openAccountId = (int) $openAccountId;
            if (in_array($openAccountId, $accIds, true)) {
                $messages = \App\Models\InstagramMessage::where('instagram_account_id', $openAccountId)
                    ->where('igsid', $openIgsid)->orderBy('id')->limit(200)->get();
                // Send a read receipt (best-effort, once / minute / thread).
                if ($messages->isNotEmpty() && \Illuminate\Support\Facades\Cache::add('ig_seen_' . $openAccountId . '_' . $openIgsid, 1, 60)) {
                    if ($openAcc = $accounts->firstWhere('id', $openAccountId)) {
                        try { (new InstagramService($openAcc))->markSeen($openIgsid); } catch (\Throwable $e) {}
                    }
                }
            }
        }
        return view('instagram.inbox', compact('accounts', 'threads', 'messages', 'openKey', 'openIgsid', 'openAccountId'));
    }

    /** Backfill the inbox from the Graph API (conversations + messages), deduped by mid. */
    public function inboxSync()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->get();
        $synced = 0;
        foreach ($accounts as $acc) {
            try {
                $svc = new InstagramService($acc);
                foreach ($svc->getConversations(30) as $c) {
                    $igsid = '';
                    foreach ((array) ($c['participants']['data'] ?? []) as $p) {
                        if ((string) ($p['id'] ?? '') !== (string) $acc->ig_user_id) { $igsid = (string) $p['id']; break; }
                    }
                    if ($igsid === '') continue;
                    foreach ($svc->getMessages((string) ($c['id'] ?? ''), 25) as $m) {
                        $mid = (string) ($m['id'] ?? '');
                        if ($mid === '') continue;
                        if (\App\Models\InstagramMessage::where('instagram_account_id', $acc->id)->where('mid', $mid)->exists()) continue;
                        $dir = ((string) ($m['from']['id'] ?? '') === (string) $acc->ig_user_id) ? 'out' : 'in';
                        \App\Models\InstagramMessage::log($acc, $igsid, $dir, (string) ($m['message'] ?? ''), 'sync', $mid);
                        $synced++;
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[IG-SYNC] ' . $e->getMessage());
            }
        }
        return back()->with('status', "Synced {$synced} new messages from Instagram.");
    }

    /**
     * Lightweight DB poll for the open thread — returns messages newer than
     * `after` for one account+igsid. Cheap (reads our DB, which the webhook
     * keeps live), so the inbox JS can poll it every few seconds like Team Inbox.
     */
    public function inboxPoll(Request $request)
    {
        $wsId  = $this->wsId();
        $accId = (int) $request->query('account_id');
        $igsid = (string) $request->query('igsid');
        $after = (int) $request->query('after', 0);

        $acc = InstagramAccount::forWorkspace($wsId)->whereKey($accId)->first();
        if (!$acc || $igsid === '') {
            return response()->json(['messages' => [], 'last_id' => $after]);
        }
        $rows = \App\Models\InstagramMessage::where('instagram_account_id', $accId)
            ->where('igsid', $igsid)
            ->where('id', '>', $after)
            ->orderBy('id')->limit(50)
            ->get(['id', 'direction', 'body', 'created_at']);

        return response()->json([
            'messages' => $rows->map(fn ($m) => [
                'id'   => (int) $m->id,
                'dir'  => (string) $m->direction,
                'body' => (string) $m->body,
                'time' => optional($m->created_at)->format('g:i A'),
            ])->values(),
            'last_id'  => (int) ($rows->last()->id ?? $after),
        ]);
    }

    /** Instagram notifications — recent inbound DMs across the workspace's accounts. */
    public function notifications()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->get();
        $acctById = $accounts->keyBy('id');
        $accIds = $accounts->pluck('id')->all();
        $items = collect();
        if ($accIds) {
            $items = \App\Models\InstagramMessage::whereIn('instagram_account_id', $accIds)
                ->where('direction', 'in')
                ->orderByDesc('id')->limit(60)->get();
        }
        return view('instagram.notifications', compact('accounts', 'items', 'acctById'));
    }

    /** Manual operator reply from the inbox — text, attachment, quick replies or human-agent. */
    public function inboxReply(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'igsid'                => 'required|string|max:64',
            'body'                 => 'nullable|string|max:1000',
            'human_agent'          => 'nullable|boolean',
            'media_file'           => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,m4v,mp3,m4a,pdf|max:25600',
            'qr_title'             => 'nullable|array',
            'qr_title.*'           => 'nullable|string|max:20',
            'qr_payload'           => 'nullable|array',
            'qb_title'             => 'nullable|array',
            'qb_title.*'           => 'nullable|string|max:20',
            'qb_type'              => 'nullable|array',
            'qb_value'             => 'nullable|array',
            'ce_title'             => 'nullable|array',
            'ce_title.*'           => 'nullable|string|max:80',
            'ce_image'             => 'nullable|array',
            'ce_subtitle'          => 'nullable|array',
            'ce_url'               => 'nullable|array',
        ]);
        $account = InstagramAccount::forWorkspace($wsId)->where('id', $data['instagram_account_id'])->first();
        if (!$account) return back()->withErrors(['instagram' => 'Account not in your workspace.']);

        $svc   = new \App\Services\Instagram\InstagramService($account);
        $igsid = $data['igsid'];
        $body  = (string) ($data['body'] ?? '');

        // 1) Media attachment (image / video / audio / file).
        if ($request->hasFile('media_file')) {
            $disk = function_exists('media_disk') ? media_disk() : 'public';
            $path = $request->file('media_file')->store('instagram-dm', $disk);
            $url  = function_exists('media_url') ? media_url($path) : asset('storage/' . $path);
            $mime = (string) $request->file('media_file')->getMimeType();
            $type = str_starts_with($mime, 'image/') ? 'image'
                : (str_starts_with($mime, 'video/') ? 'video'
                : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'));
            $r = $svc->sendMediaDm($igsid, $type, $url);
            if (!empty($r['ok'])) {
                \App\Models\InstagramMessage::log($account, $igsid, 'out', '[' . $type . ']', 'manual', $r['mid'] ?? null);
                return back()->with('status', 'Attachment sent.');
            }
            return back()->withErrors(['instagram' => 'Attachment failed: ' . ($r['error'] ?? 'unknown')]);
        }

        // 2) Quick-reply buttons (need body text + at least one title).
        $titles = array_values(array_filter((array) ($data['qr_title'] ?? []), fn ($t) => trim((string) $t) !== ''));
        if (!empty($titles) && $body !== '') {
            $payloads = (array) $request->input('qr_payload', []);
            $replies  = [];
            foreach ($titles as $i => $t) $replies[] = ['title' => $t, 'payload' => (string) ($payloads[$i] ?? $t)];
            $r = $svc->sendQuickReplies($igsid, $body, $replies);
            if (!empty($r['ok'])) {
                \App\Models\InstagramMessage::log($account, $igsid, 'out', $body, 'manual', $r['mid'] ?? null);
                return back()->with('status', 'Quick replies sent.');
            }
            return back()->withErrors(['instagram' => 'Send failed: ' . ($r['error'] ?? 'unknown')]);
        }

        // 2b) Button template (need body text + at least one button title).
        $btnTitles = array_values(array_filter((array) $request->input('qb_title', []), fn ($t) => trim((string) $t) !== ''));
        if (!empty($btnTitles) && $body !== '') {
            $types  = (array) $request->input('qb_type', []);
            $values = (array) $request->input('qb_value', []);
            $buttons = [];
            foreach ($btnTitles as $i => $t) {
                $bt = ($types[$i] ?? 'postback') === 'web_url' ? 'web_url' : 'postback';
                $b  = ['type' => $bt, 'title' => $t];
                if ($bt === 'web_url') $b['url'] = (string) ($values[$i] ?? '');
                else $b['payload'] = (string) ($values[$i] ?? $t);
                $buttons[] = $b;
            }
            $r = $svc->sendButtonTemplate($igsid, $body, $buttons);
            if (!empty($r['ok'])) {
                \App\Models\InstagramMessage::log($account, $igsid, 'out', $body, 'manual', $r['mid'] ?? null);
                return back()->with('status', 'Buttons sent.');
            }
            return back()->withErrors(['instagram' => 'Send failed: ' . ($r['error'] ?? 'unknown')]);
        }

        // 2c) Generic-template carousel (2-10 element cards; needs no body).
        $elTitles = array_values(array_filter((array) $request->input('ce_title', []), fn ($t) => trim((string) $t) !== ''));
        if (!empty($elTitles)) {
            $imgs = (array) $request->input('ce_image', []);
            $subs = (array) $request->input('ce_subtitle', []);
            $urls = (array) $request->input('ce_url', []);
            $elements = [];
            foreach ($elTitles as $i => $t) {
                $el = ['title' => mb_substr($t, 0, 80)];
                if (!empty($imgs[$i])) $el['image_url'] = (string) $imgs[$i];
                if (!empty($subs[$i])) $el['subtitle'] = mb_substr((string) $subs[$i], 0, 80);
                if (!empty($urls[$i])) $el['default_action'] = ['type' => 'web_url', 'url' => (string) $urls[$i]];
                $elements[] = $el;
            }
            $r = $svc->sendGenericTemplate($igsid, $elements);
            if (!empty($r['ok'])) {
                \App\Models\InstagramMessage::log($account, $igsid, 'out', '[carousel]', 'manual', $r['mid'] ?? null);
                return back()->with('status', 'Carousel sent.');
            }
            return back()->withErrors(['instagram' => 'Send failed: ' . ($r['error'] ?? 'unknown')]);
        }

        // 3) Plain text (optionally as 7-day human-agent message).
        if ($body === '') return back()->withErrors(['instagram' => 'Type a message or attach a file.']);
        $r = ($data['human_agent'] ?? false) ? $svc->sendHumanAgent($igsid, $body) : $svc->sendDm($igsid, $body);
        if (!empty($r['ok'])) {
            \App\Models\InstagramMessage::log($account, $igsid, 'out', $body, 'manual', $r['mid'] ?? null);
            return back()->with('status', 'Reply sent.');
        }
        return back()->withErrors(['instagram' => 'Send failed: ' . ($r['error'] ?? 'unknown') . ' (the 24h window may be closed).']);
    }

    /** React (or un-react) to an inbound DM. */
    public function inboxReact(Request $request)
    {
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'igsid'                => 'required|string|max:64',
            'message_id'           => 'required|string|max:191',
            'reaction'             => 'nullable|string|max:40',
            'remove'               => 'nullable|boolean',
        ]);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $svc = new InstagramService($acc);
        $r = ($d['remove'] ?? false)
            ? $svc->removeReaction($d['igsid'], $d['message_id'])
            : $svc->sendReaction($d['igsid'], $d['message_id'], $d['reaction'] ?? 'love');
        return $this->igResult($r, ($d['remove'] ?? false) ? 'Reaction removed.' : 'Reaction sent.');
    }

    /** Composer — post to Instagram + optionally wire a comment→DM rule on the new post. */
    public function composer()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $igFlows  = \App\Models\Flow::where('workspace_id', $wsId)->where('flow_type', 'instagram')->orderByDesc('id')->get();
        $scheduled = \App\Models\InstagramScheduledPost::where('workspace_id', $wsId)
            ->where('status', 'pending')->orderBy('scheduled_at')->limit(10)->get();
        // Daily publishing quota for the first connected account (cached 10 min to avoid an API hit per load).
        $publishLimit = null;
        if ($acc = $accounts->first()) {
            $publishLimit = \Illuminate\Support\Facades\Cache::remember('ig_pub_limit_' . $acc->id, 600, fn () => (new InstagramService($acc))->publishingLimit());
        }
        return view('instagram.composer', compact('accounts', 'igFlows', 'scheduled', 'publishLimit'));
    }

    public function composerPublish(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'media_type'           => 'nullable|in:image,reels,story,carousel',
            'image_url'            => 'nullable|url|max:1024',
            'video_url'            => 'nullable|url|max:1024',
            'carousel_urls'        => 'nullable|string|max:4000',
            'caption'              => 'nullable|string|max:2200',
            // Uploaded media (alternative to pasting a URL).
            'media_file'           => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,m4v|max:102400',
            'media_files.*'        => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,m4v|max:102400',
            // Optional comment→DM automation to wire onto the new post.
            'auto_keyword'         => 'nullable|string|max:500',
            'auto_public_reply'    => 'nullable|string|max:500',
            'auto_dm'              => 'nullable|string|max:1000',
            'auto_flow_id'         => 'nullable|integer',
            'schedule_at'          => 'nullable|date',
            // Reel advanced options (forwarded to publishReel for media_type=reels).
            'cover_url'            => 'nullable|url|max:1024',
            'thumb_offset'        => 'nullable|integer|min:0',
            'share_to_feed'        => 'nullable|boolean',
            'audio_name'           => 'nullable|string|max:120',
        ]);
        $type = $data['media_type'] ?? 'image';

        // Uploaded media → store on the active (cloud/local) disk and use its
        // public URL. Meta publishes from a public HTTPS link, so this works
        // end-to-end on an https host (and cloud storage returns an https URL).
        $mediaDisk = function_exists('media_disk') ? media_disk() : 'public';
        $mkUrl = fn ($p) => function_exists('media_url') ? media_url($p) : asset('storage/' . $p);
        if ($request->hasFile('media_file')) {
            $f = $request->file('media_file');
            $url = $mkUrl($f->store('instagram-media', $mediaDisk));
            if (str_starts_with((string) $f->getMimeType(), 'video')) {
                $data['video_url'] = $url;
            } else {
                $data['image_url'] = $url;
            }
        }
        if ($request->hasFile('media_files')) {
            $urls = [];
            foreach ($request->file('media_files') as $f) {
                $urls[] = $mkUrl($f->store('instagram-media', $mediaDisk));
            }
            if ($urls) {
                $data['carousel_urls'] = trim(($data['carousel_urls'] ?? '') . "\n" . implode("\n", $urls));
            }
        }

        $account = InstagramAccount::forWorkspace($wsId)->where('id', $data['instagram_account_id'])->first();
        if (!$account) return back()->withErrors(['instagram' => 'Account not in your workspace.'])->withInput();

        // Per-type required media URL(s) + HTTPS check (Meta requires public HTTPS).
        if ($err = self::validateMediaInput($type, $data)) {
            return back()->withErrors(['instagram' => $err])->withInput();
        }

        // Schedule for later → hold it; the no-cron sweep publishes it when due.
        if (!empty($data['schedule_at']) && \Illuminate\Support\Carbon::parse($data['schedule_at'])->isFuture()) {
            \App\Models\InstagramScheduledPost::create([
                'workspace_id'         => $wsId,
                'instagram_account_id' => $account->id,
                'media_type'           => $type,
                'image_url'            => $data['image_url'] ?? null,
                'video_url'            => $data['video_url'] ?? null,
                'media_urls'           => $type === 'carousel' ? self::parseCarousel((string) ($data['carousel_urls'] ?? '')) : null,
                'caption'              => $data['caption'] ?? null,
                'scheduled_at'         => \Illuminate\Support\Carbon::parse($data['schedule_at']),
                'status'               => 'pending',
                'auto_keyword'         => $data['auto_keyword'] ?? null,
                'auto_public_reply'    => $data['auto_public_reply'] ?? null,
                'auto_dm'              => $data['auto_dm'] ?? null,
                'auto_flow_id'         => !empty($data['auto_flow_id']) ? (int) $data['auto_flow_id'] : null,
            ]);
            return redirect('/instagram')->with('status', ucfirst($type) . ' scheduled for ' . \Illuminate\Support\Carbon::parse($data['schedule_at'])->toDayDateTimeString() . '.');
        }

        $res = self::publishByType(new \App\Services\Instagram\InstagramService($account), $type, $data);
        if (empty($res['ok'])) {
            return back()->withErrors(['instagram' => 'Publish failed: ' . ($res['error'] ?? 'unknown')])->withInput();
        }
        $mediaId = (string) ($res['media_id'] ?? '');

        // Wire the comment→DM rule onto THIS post if the user filled it in.
        if (!empty($data['auto_dm']) || (!empty($data['auto_flow_id']) && !empty($data['auto_keyword']))) {
            $isFlow = !empty($data['auto_flow_id']);
            InstagramAutomation::create([
                'workspace_id'         => $wsId,
                'instagram_account_id' => $account->id,
                'type'                 => $isFlow ? 'flow' : 'comment_to_dm',
                'name'                 => 'Post ' . substr($mediaId, -6) . ' · comment→DM',
                'trigger_keyword'      => $data['auto_keyword'] ?? null,
                'match_mode'           => !empty($data['auto_keyword']) ? 'contains' : 'any',
                'post_id'              => $mediaId,                 // scope to the post we just made
                'public_reply'         => $data['auto_public_reply'] ?? null,
                'dm_message'           => $data['auto_dm'] ?? '',
                'flow_id'              => $isFlow ? (int) $data['auto_flow_id'] : null,
                'is_active'            => true,
            ]);
            return redirect('/instagram')->with('status', 'Posted to Instagram + comment→DM automation armed on the new post.');
        }

        return redirect('/instagram')->with('status', 'Posted to Instagram. Media ID ' . $mediaId . '.');
    }

    /** Validate the required media URL(s) for the chosen media type. Null = OK. */
    public static function validateMediaInput(string $type, array $data): ?string
    {
        $https = fn ($u) => is_string($u) && str_starts_with($u, 'https://');
        return match ($type) {
            'reels'    => $https($data['video_url'] ?? '') ? null : 'Reel needs a public HTTPS MP4/MOV video URL.',
            'story'    => ($https($data['image_url'] ?? '') || $https($data['video_url'] ?? '')) ? null : 'Story needs a public HTTPS image OR video URL.',
            'carousel' => count(self::parseCarousel((string) ($data['carousel_urls'] ?? ''))) >= 2 ? null : 'Carousel needs 2–10 public HTTPS image/video URLs (one per line).',
            default    => $https($data['image_url'] ?? '') ? null : 'Image URL must be a public HTTPS JPEG.',
        };
    }

    /** Parse a textarea of URLs (one per line/comma) into [['type'=>image|video,'url'=>], …]. */
    public static function parseCarousel(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,]+/', $raw) as $line) {
            $u = trim((string) $line);
            if (!str_starts_with($u, 'https://')) continue;
            $isVid = (bool) preg_match('/\.(mp4|mov|m4v)(\?|$)/i', $u);
            $out[] = ['type' => $isVid ? 'video' : 'image', 'url' => $u];
            if (count($out) >= 10) break;
        }
        return $out;
    }

    /**
     * Dispatch a publish by media type to the verified InstagramService method.
     * Static so the scheduled-post command reuses the exact same routing.
     * $data keys: image_url, video_url, carousel_urls OR media_urls(array), caption.
     */
    public static function publishByType(\App\Services\Instagram\InstagramService $svc, string $type, array $data): array
    {
        $caption = (string) ($data['caption'] ?? '');
        switch ($type) {
            case 'reels':
                $opts = array_filter([
                    'cover_url'     => $data['cover_url'] ?? null,
                    'thumb_offset'  => isset($data['thumb_offset']) && $data['thumb_offset'] !== '' ? (int) $data['thumb_offset'] : null,
                    'share_to_feed' => array_key_exists('share_to_feed', $data) ? (bool) $data['share_to_feed'] : null,
                    'audio_name'    => $data['audio_name'] ?? null,
                ], fn ($v) => $v !== null && $v !== '');
                return $svc->publishReel((string) ($data['video_url'] ?? ''), $caption, $opts);
            case 'story':
                $vid = !empty($data['video_url']);
                return $svc->publishStory((string) ($vid ? $data['video_url'] : ($data['image_url'] ?? '')), $vid);
            case 'carousel':
                $items = is_array($data['media_urls'] ?? null)
                    ? $data['media_urls']
                    : self::parseCarousel((string) ($data['carousel_urls'] ?? ''));
                return $svc->publishCarousel($items, $caption);
            default:
                return $svc->publishImage((string) ($data['image_url'] ?? ''), $caption);
        }
    }

    /** Analytics — real IG insights (reach / engagement) + our DM volume. */
    public function analytics(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $accId    = (int) $request->query('account', 0);
        $account  = $accId ? $accounts->firstWhere('id', $accId) : $accounts->first();

        $insights = [];
        if ($account) {
            // Cache 10 min — insights are slow + rate-limited.
            $insights = \Illuminate\Support\Facades\Cache::remember(
                "ig_insights_{$account->id}", 600,
                fn () => (new \App\Services\Instagram\InstagramService($account))->accountInsights()
            );
        }
        $reach   = array_sum(array_column($insights['reach'] ?? [], 'v'));
        // `profile_views` was deprecated at account level (2025-01-08). Use the
        // verified total_value `accounts_engaged` counter instead.
        $profile = (int) ($insights['_totals']['accounts_engaged'] ?? 0);

        // Our own DM volume — 14-day daily buckets (in vs out) from the log.
        $labels = []; $inSeries = []; $outSeries = [];
        for ($i = 13; $i >= 0; $i--) $labels[now()->subDays($i)->toDateString()] = 0;
        $dmIn = $labels; $dmOut = $labels;
        if ($account) {
            $rows = \App\Models\InstagramMessage::where('instagram_account_id', $account->id)
                ->where('created_at', '>=', now()->subDays(14)->startOfDay())
                ->get(['direction', 'created_at']);
            foreach ($rows as $r) {
                $d = $r->created_at->toDateString();
                if (!array_key_exists($d, $dmIn)) continue;
                if ($r->direction === 'in') $dmIn[$d]++; else $dmOut[$d]++;
            }
        }
        $labels    = array_keys($dmIn);
        $inSeries  = array_values($dmIn);
        $outSeries = array_values($dmOut);

        // Recent posts for the per-post insights drill-down.
        $media = $account
            ? \Illuminate\Support\Facades\Cache::remember("ig_media_{$account->id}", 600, fn () => (new InstagramService($account))->getMedia(12))
            : [];

        return view('instagram.analytics', compact('accounts', 'account', 'insights', 'reach', 'profile', 'labels', 'inSeries', 'outSeries', 'media'));
    }

    /** Bulk-DM composer — sends to everyone inside the 24h messaging window. */
    public function broadcast()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $cutoff = now()->subHours(24);
        // Per-account in-window reachable count (people who DMed in the last 24h).
        $reach = [];
        foreach ($accounts as $acc) {
            $reach[$acc->id] = \App\Models\InstagramMessage::where('instagram_account_id', $acc->id)
                ->where('direction', 'in')->where('created_at', '>=', $cutoff)->distinct('igsid')->count('igsid');
        }
        $recent = \App\Models\InstagramBroadcast::where('workspace_id', $wsId)->orderByDesc('id')->limit(10)->get();
        // In-window contacts for the "pick specific people" segment (first account).
        $contacts = collect();
        if ($first = $accounts->first()) {
            $contacts = \App\Models\InstagramMessage::where('instagram_account_id', $first->id)
                ->where('direction', 'in')->where('created_at', '>=', $cutoff)
                ->orderByDesc('id')->limit(300)->get(['igsid', 'body'])
                ->unique('igsid')->take(80)->values();
        }
        return view('instagram.broadcast', compact('accounts', 'reach', 'recent', 'contacts'));
    }

    public function broadcastSend(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'body'                 => 'required|string|max:1000',
            'segment'              => 'nullable|in:all,pick',
            'igsids'               => 'nullable|array',
            'igsids.*'             => 'string|max:64',
        ]);
        $account = InstagramAccount::forWorkspace($wsId)->where('id', $data['instagram_account_id'])->first();
        if (!$account) return back()->withErrors(['instagram' => 'Account not in your workspace.']);

        // Everyone currently inside the 24h window (ban-safe base set).
        $cutoff   = now()->subHours(24);
        $inWindow = \App\Models\InstagramMessage::where('instagram_account_id', $account->id)
            ->where('direction', 'in')->where('created_at', '>=', $cutoff)
            ->distinct()->pluck('igsid')->values()->all();

        // Segment: "pick" keeps only chosen IGSIDs that are STILL in-window (policy stays intact).
        if (($data['segment'] ?? 'all') === 'pick' && !empty($data['igsids'])) {
            $igsids = array_values(array_intersect($inWindow, $data['igsids']));
        } else {
            $igsids = $inWindow;
        }

        if (empty($igsids)) {
            return back()->withErrors(['instagram' => 'No contacts inside the 24-hour window match — bulk DMs can only reach people who messaged you in the last 24h.'])->withInput();
        }

        $bcast = \App\Models\InstagramBroadcast::create([
            'workspace_id'         => $wsId,
            'instagram_account_id' => $account->id,
            'body'                 => $data['body'],
            'recipients'           => $igsids,
            'total'                => count($igsids),
            'cursor'               => 0,
            'sent'                 => 0,
            'failed'               => 0,
            'status'               => 'pending',
        ]);

        // Per-recipient ledger — one pending row each (drives the drill-down view).
        $now  = now();
        $rows = array_map(fn ($ig) => [
            'broadcast_id' => $bcast->id, 'igsid' => $ig, 'status' => 'pending',
            'created_at' => $now, 'updated_at' => $now,
        ], $igsids);
        foreach (array_chunk($rows, 500) as $chunk) {
            \App\Models\InstagramBroadcastRecipient::insert($chunk);
        }

        return back()->with('status', 'Bulk DM queued to ' . count($igsids) . ' in-window contacts. It drains safely in the background (Meta limit ~200/hr).');
    }

    /** Per-recipient drill-down for one bulk DM. */
    public function broadcastShow(int $id)
    {
        $bcast = \App\Models\InstagramBroadcast::where('workspace_id', $this->wsId())->where('id', $id)->firstOrFail();
        $recipients = \App\Models\InstagramBroadcastRecipient::where('broadcast_id', $id)->orderBy('id')->paginate(50);
        return view('instagram.broadcast-show', compact('bcast', 'recipients'));
    }

    public function automationToggle(int $id)
    {
        $a = InstagramAutomation::where('workspace_id', $this->wsId())->findOrFail($id);
        $a->update(['is_active' => !$a->is_active]);
        return back()->with('status', $a->is_active ? 'Automation turned on.' : 'Automation paused.');
    }

    public function automationDestroy(int $id)
    {
        InstagramAutomation::where('workspace_id', $this->wsId())->where('id', $id)->delete();
        return back()->with('status', 'Automation deleted.');
    }
}
