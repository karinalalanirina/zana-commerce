<?php

namespace App\Http\Controllers;

use App\Events\Inbox\ConversationAssigned;
use App\Events\Inbox\ConversationUpdated;
use App\Events\Inbox\MentionReceived;
use App\Events\Inbox\MessageReceived;
use App\Events\Inbox\NoteAdded;
use App\Models\AgentStatus;
use App\Models\AiAgent;
use App\Models\AiProviderKey;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationNote;
use App\Models\ConversationParticipant;
use App\Models\InboxNotification;
use App\Models\InboxMessage;
use App\Models\Message;
use App\Models\RoutingRule;
use App\Models\SavedReply;
use App\Models\SlaPolicy;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Services\Inbox\AssignmentService;
use App\Services\Inbox\AuditLogger;
use App\Services\Inbox\NotificationDispatcher;
use App\Services\Inbox\SlaTracker;
use App\Support\WorkspacePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Team-inbox API. The blade view hands the front-end a static shell;
 * everything dynamic flows through the JSON endpoints here.
 *
 * Auth: every method assumes the routes are wrapped in
 * `auth + workspace.member` middleware. The `current_workspace_id`
 * column is the scope; ConversationPolicy enforces the per-action gate.
 *
 * Note ordering: state mutation → record event → dispatch broadcast →
 * dispatch in-app notification. Always in that order so a failure in
 * the broadcast/notification step doesn't leave the audit log lying.
 */
class TeamInboxController extends Controller
{
    public function __construct(
        private AssignmentService $assignment,
        private SlaTracker $sla,
        private NotificationDispatcher $notify,
    ) {
    }

    public function index()
    {
        // Count connected devices visible to the team-inbox getting-started
        // panel. Tries 3 lookups so the count is right regardless of how
        // the device's ownership / status row sits. Whichever finds the
        // most rows wins.
        $user = \Illuminate\Support\Facades\Auth::user();
        $wsId = $user?->current_workspace_id;

        // Visiting the inbox = "I've seen what's there". Bump the
        // last-seen-at so the global notification widget only counts
        // messages arriving from this moment on.
        if ($user) {
            $user->forceFill(['inbox_last_seen_at' => now()])->save();
        }
        $userIds = $wsId
            ? \App\Models\User::query()->where('current_workspace_id', $wsId)->pluck('id')
            : collect([$user?->id]);

        $countWorkspace = \App\Models\Device::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'connected')
            ->where('active', true)
            ->count();
        $countMine = \App\Models\Device::query()
            ->where('user_id', $user?->id)
            ->where('status', 'connected')
            ->where('active', true)
            ->count();
        $countAny = \App\Models\Device::query()
            ->where('user_id', $user?->id)
            ->where('active', true)
            ->count();

        // Multi-engine: a workspace can run Baileys + WABA + Twilio at once,
        // so the getting-started "devices connected" count must SUM connected
        // senders across EVERY enabled engine. Baileys contributes its device-
        // table count (max of workspace/mine/any); WABA / Twilio each add their
        // connected WaProviderConfig rows. For a single-engine workspace
        // enginesFor() == [default], so this equals the old single branch
        // (byte-identical): baileys -> max(...) only; waba/twilio -> that
        // provider's connected count only.
        $connectedDevices = 0;
        foreach (\App\Services\WorkspaceEngine::enginesFor($wsId) as $engine) {
            if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
                $connectedDevices += max($countWorkspace, $countMine, $countAny);
            } elseif ($wsId) {
                $connectedDevices += \App\Models\WaProviderConfig::query()
                    ->where('workspace_id', $wsId)
                    ->where('provider', $engine)
                    ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                    ->count();
            }
        }

        // Pull the actual connected device(s) so the getting-started
        // panel can show the phone number to test against.
        $connectedDeviceList = \App\Models\Device::query()
            ->whereIn('user_id', $userIds->isEmpty() ? [$user?->id] : $userIds->all())
            ->where('active', true)
            ->orderByDesc('id')
            ->get(['id', 'device_name', 'country_code', 'phone_number'])
            ->map(fn ($d) => [
                'id'    => $d->id,
                'name'  => (string) $d->device_name,
                'phone' => trim('+' . ltrim($d->country_code ?: '', '+') . ' ' . $d->phone_number),
            ])
            ->values();

        \Illuminate\Support\Facades\Log::info('[TEAM-INBOX] device count', [
            'auth_user_id'      => $user?->id,
            'auth_user_email'   => $user?->email,
            'workspace_id'      => $wsId,
            'workspace_users'   => $userIds->all(),
            'count_workspace'   => $countWorkspace,
            'count_mine'        => $countMine,
            'count_any_active'  => $countAny,
            'final'             => $connectedDevices,
        ]);

        // Templates available to the Template tab — same library
        // /wa-campaigns/create reads from. We deliberately use
        // WaTemplate (not the simpler ChatTemplate snippets) so the
        // operator sees one template list across the app.
        //
        // Approval gating mirrors the campaign controller:
        // - WABA (Meta Cloud API) requires `approved`/`public`.
        // - Baileys has no Meta approval flow — show ALL statuses.
        $defaultSendMethod = \App\Models\SystemSetting::get('default_send_method', 'baileys');
        $allowedMethods    = \App\Models\SystemSetting::get('allowed_send_methods', ['baileys']);
        $allowedMethods    = is_array($allowedMethods) ? $allowedMethods : [$allowedMethods];
        $activeMethod      = in_array($defaultSendMethod, $allowedMethods, true) ? $defaultSendMethod : ($allowedMethods[0] ?? 'baileys');
        $requiresApproved  = $activeMethod === 'waba';

        // Workspace-scoped so every template the workspace owns (+ admin
        // globals) shows, not only rows whose user_id is a current member.
        $tplQuery = \App\Models\WaTemplate::query()
            ->forCurrentWorkspace()
            ->orderByDesc('id');
        if ($requiresApproved) {
            $tplQuery->approved();
        }
        // Pulling whole rows so the encrypted casts on header/footer/
        // buttons fire — selecting a subset of columns would skip them.
        $chatTemplates = $tplQuery
            ->get()
            ->map(fn ($t) => [
                'id'       => $t->id,
                'title'    => (string) $t->template_name,
                'category' => (string) ($t->category ?: 'utility'),
                'header'   => (string) ($t->header ?: ''),
                'body'     => (string) $t->template_body,
                'footer'   => (string) ($t->footer ?: ''),
                'buttons'  => is_array($t->buttons) ? $t->buttons : [],
                'media'    => (string) ($t->attachment_type ?: ''),
                'status'   => (string) ($t->status ?: 'approved'),
            ])
            ->values();

        // Every channel the operator can narrow the queue to. Lists ALL
        // paired numbers — connected OR not — because you still want to
        // filter to a disconnected number's past chats. WABA + Twilio
        // accounts appear too: a WABA/Twilio thread stores its
        // wa_provider_configs id in Conversation.device_id
        // (device-unification), so the same ?device_id= filter narrows them.
        $baileysOptions = \App\Models\Device::query()
            ->whereIn('user_id', $userIds->isEmpty() ? [$user?->id] : $userIds->all())
            ->orderByDesc('active')
            ->orderByDesc('id')
            ->get(['id', 'device_name', 'country_code', 'phone_number'])
            ->map(fn ($d) => [
                'id'     => $d->id,
                'label'  => trim((string) $d->device_name) ?: ('Device #' . $d->id),
                'phone'  => '+' . ltrim((string) $d->country_code, '+') . ' ' . $d->phone_number,
                'engine' => __('Unofficial API'),
            ])
            ->values();

        $providerOptions = $wsId
            ? \App\Models\WaProviderConfig::query()
                ->where('workspace_id', $wsId)
                ->whereIn('provider', ['waba', 'twilio'])
                ->where('status', \App\Models\WaProviderConfig::STATUS_CONNECTED)
                ->orderBy('provider')
                ->get(['id', 'provider', 'display_label', 'phone_number'])
                ->map(fn ($c) => [
                    'id'     => $c->id,
                    'label'  => trim((string) $c->display_label)
                        ?: (($c->provider === 'waba' ? 'WABA' : 'Twilio') . ' #' . $c->id),
                    'phone'  => '+' . ltrim(preg_replace('/\D+/', '', (string) $c->phone_number), '+'),
                    'engine' => $c->provider === 'waba' ? __('Meta (WABA)') : __('Twilio'),
                ])
                ->values()
            : collect();

        $deviceFilterOptions = $baileysOptions->concat($providerOptions)->values();

        return view('user.team-inbox.index', compact('connectedDevices', 'connectedDeviceList', 'chatTemplates', 'deviceFilterOptions'));
    }

    // ----------------------------------------------------------------
    // Bootstrap — initial payload the SPA needs on load.
    // ----------------------------------------------------------------

    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $user->current_workspace_id;

        $perms = collect(WorkspacePermissions::PERMISSIONS)
            ->mapWithKeys(fn ($p) => [$p => WorkspacePermissions::userCan($user, $p, $wsId)])
            ->all();

        $teams = Team::forWorkspace($wsId)->orderBy('sort')->get(['id','name','slug','color','assignment_strategy','is_default','device_ids']);

        $members = User::query()
            ->whereHas('workspaces', fn ($q) => $q->where('workspaces.id', $wsId))
            ->select(['id','name','email'])
            ->get();

        $statuses = AgentStatus::forWorkspace($wsId)->get(['user_id','status','status_message','last_seen_at','current_load']);
        $statusByUser = $statuses->keyBy('user_id');

        $tags = Tag::forWorkspace($wsId)->get(['id','name','slug','color']);

        $myStatus = $user->agentStatusForCurrent();

        $unread = InboxNotification::forUser($user->id)->forWorkspace($wsId)->unread()->count();

        return response()->json([
            'me' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => $user->workspaceRole($wsId),
                'avatar'     => null,
                'status'     => $myStatus?->status ?? 'online',
                'load'       => $myStatus?->current_load ?? 0,
                'unread_notifications' => $unread,
            ],
            'permissions' => $perms,
            'teams'       => $teams,
            'tags'        => $tags,
            'members'     => $members->map(fn ($m) => [
                'id' => $m->id, 'name' => $m->name, 'email' => $m->email,
                'status'      => $statusByUser->get($m->id)?->status ?? 'offline',
                'last_seen_at'=> $statusByUser->get($m->id)?->last_seen_at,
                'load'        => $statusByUser->get($m->id)?->current_load ?? 0,
            ])->values(),
            'sla_policies'  => SlaPolicy::forWorkspace($wsId)->get(),
            'saved_replies' => SavedReply::forWorkspace($wsId)->accessibleBy($user->id)
                ->orderByDesc('used_count')->get()
                ->map(fn ($r) => [
                    'id'         => $r->id,
                    'shortcut'   => $r->shortcut,
                    'title'      => $r->title,
                    'body'       => (string) $r->body,
                    'category'   => $r->category,
                    'used_count' => $r->used_count,
                    'user_id'    => $r->user_id,
                ])->values(),
            'routing_rules' => RoutingRule::forWorkspace($wsId)->orderBy('sort')->get(),
            // Flows + approved templates surface in the routing-rule
            // form so admins can pick a flow to trigger or a template to
            // auto-reply with, without leaving the inbox.
            'flows'         => \App\Models\Flow::query()
                ->forCurrentWorkspace()
                ->where('is_active', true)
                ->orderBy('flow_name')
                ->get(['id', 'flow_name', 'category']),
            'templates'     => \App\Models\WaTemplate::query()
                ->forCurrentWorkspace()
                ->approved()
                ->orderBy('template_name')
                ->get(['id', 'template_name', 'language']),
            'business_hours' => optional($user->currentWorkspace)->business_hours,
            'ai_agents'     => AiAgent::forWorkspace($wsId)->orderBy('id')->get()->map->toCard()->values(),
            'ai_keys'       => AiProviderKey::query()
                ->where('workspace_id', $wsId)
                ->get(['id', 'provider', 'is_active'])
                ->map(fn ($k) => ['id' => $k->id, 'provider' => $k->provider, 'is_active' => $k->is_active])
                ->values(),
            // Active paired devices for this workspace, used by:
            // - the routing-rule builder's `incoming_device` condition
            // - the AI agent editor's per-agent device scoping
            // - any future inbox surface that needs to filter by device
            // Single-device workspaces will still see a 1-element list;
            // UI render-guards (count > 1) keep dropdowns hidden when
            // there's nothing to pick.
            'devices'       => \App\Models\Device::query()
                ->whereIn('user_id', \App\Models\User::query()
                    ->where('current_workspace_id', $wsId)
                    ->pluck('id'))
                ->where('active', true)
                ->orderByDesc('id')
                ->get(['id', 'device_name', 'country_code', 'phone_number'])
                ->map(fn ($d) => [
                    'id'    => $d->id,
                    'label' => trim((string) $d->device_name) ?: ('Device #' . $d->id),
                    'phone' => '+' . ltrim((string) $d->country_code, '+') . ' ' . $d->phone_number,
                ])
                ->values(),
        ]);
    }

    // ----------------------------------------------------------------
    // Queue list — the left-pane scrolling stream.
    // ----------------------------------------------------------------

    /**
     * Lightweight unread-summary for the global notification widget.
     * Returns the total unread conversation count + the top 5 most
     * recently active unread conversations so the popup can preview
     * them without a second roundtrip.
     *
     * Server-side cached for 5 seconds per (user, workspace) so a
     * polling fleet of operators doesn't hammer the DB. Caller-side
     * throttle on the route is 60/min — well above the widget's 4/min
     * default poll, so spikes (e.g. 3 tabs open) still don't hit 429.
     */
    public function unreadSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $user->current_workspace_id;
        if (!$wsId) return response()->json(['total' => 0, 'items' => []]);

        // First-ever poll → silently mark "seen now" so the existing
        // backlog (whatever the unread count already was) doesn't show
        // up as "new". Genuine new messages from this moment forward
        // will then appear.
        if (!$user->inbox_last_seen_at) {
            $user->forceFill(['inbox_last_seen_at' => now()])->save();
        }
        $since = $user->inbox_last_seen_at;

        $cacheKey = 'inbox_unread_summary_' . $user->id . '_' . $wsId . '_' . ($since?->timestamp ?? 0);
        try {
        $payload = \Illuminate\Support\Facades\Cache::remember($cacheKey, 5, function () use ($wsId, $since) {
            $base = \App\Models\Conversation::query()
                ->where('workspace_id', $wsId)
                ->forCurrentEngine()
                ->deviceAlive()
                ->whereIn('inbox_status', ['open', 'pending'])
                ->where('unread_count', '>', 0)
                ->where('last_inbound_at', '>', $since);

            $rows = (clone $base)
                ->orderByDesc('last_inbound_at')
                ->limit(5)
                ->get(['id', 'title', 'preview', 'unread_count', 'last_inbound_at', 'last_message_at']);

            // "total" = conversations with new inbound since last seen.
            // Counting conversations (not raw message rows) matches what
            // the operator sees in the popup list.
            $total = (int) (clone $base)->count();

            return [
                'total' => $total,
                'items' => $rows->map(fn ($c) => [
                    'id'              => $c->id,
                    'title'           => mask_phone((string) ($c->title ?? '—')),
                    'preview'         => mb_substr((string) ($c->preview ?? ''), 0, 80),
                    'unread_count'    => (int) $c->unread_count,
                    'last_message_at' => $c->last_inbound_at ?: $c->last_message_at,
                ])->values(),
            ];
        });
        } catch (\Throwable $e) {
            // Never 500 the global notification badge. If a model scope is
            // missing on a not-yet-fully-synced deployment (e.g. Conversation
            // without scopeDeviceAlive), degrade to an empty summary instead.
            \Illuminate\Support\Facades\Log::warning('[unreadSummary] '.$e->getMessage());
            $payload = ['total' => 0, 'items' => []];
        }

        return response()->json($payload);
    }

    /**
     * Reset the "new messages" counter for the global notification widget.
     * Called from the inbox index/kanban views and exposed as an explicit
     * endpoint the widget can hit if needed.
     */
    public function markInboxSeen(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['inbox_last_seen_at' => now()])->save();
        return response()->json(['ok' => true, 'inbox_last_seen_at' => $user->inbox_last_seen_at]);
    }

    public function queue(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $user->current_workspace_id;

        // Sweep any due escalations inline on every poll. This replaces
        // the `inbox:escalate` Artisan command — we don't want to depend
        // on `schedule:run` cron being configured. Capped + cheap-indexed
        // so it can't dominate request time, and gated to once-per-30s
        // per workspace via cache to avoid hammering it on busy queues.
        $cacheKey = "inbox:esc-sweep:{$wsId}";
        if (!cache()->has($cacheKey)) {
            cache()->put($cacheKey, 1, now()->addSeconds(30));
            try {
                // Limit raised from 20 → 200 because the cache gate
                // already caps frequency to once per 30s per workspace,
                // so an occasional 200-row sweep is fine and avoids
                // backlog on busy queues that exceeded 20 due items
                // between sweeps.
                $due = Conversation::query()
                    ->forWorkspace($wsId)
                    ->whereNotNull('escalation_due_at')
                    ->where('escalation_due_at', '<=', now())
                    ->whereIn('inbox_status', ['open', 'pending'])
                    ->whereNull('first_response_at')
                    ->limit(200)
                    ->get();
                if ($due->isNotEmpty()) {
                    $engine = app(\App\Services\Inbox\RoutingEngine::class);
                    foreach ($due as $c) {
                        try { $engine->applyEscalation($c); }
                        catch (\Throwable $e) {
                            $c->forceFill(['escalation_due_at' => null, 'escalation_action' => null])->save();
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Never let the sweep break the queue response itself.
                \Illuminate\Support\Facades\Log::warning('[INBOX] escalation sweep failed: ' . $e->getMessage());
            }
        }

        // SLA breach sweep — flips conversations to sla_breached when
        // their sla_first_response_due / sla_resolution_due is past
        // and they're still unanswered/unresolved. Same inline pattern
        // as the escalation sweep — no `schedule:run` dependency. Cap
        // is generous (200) because SLA scanning is cheap (only
        // indexed datetime cols) and the cache gate keeps frequency
        // tame.
        // Global cache key (not per-workspace) because SlaTracker::sweepBreaches
        // walks every workspace in one pass — gating per-ws here would let
        // multiple workspaces redundantly trigger the same full sweep.
        if (!cache()->has('inbox:sla-sweep')) {
            cache()->put('inbox:sla-sweep', 1, now()->addSeconds(60));
            try {
                app(\App\Services\Inbox\SlaTracker::class)->sweepBreaches();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[INBOX] SLA sweep failed: ' . $e->getMessage());
            }
        }

        // Snooze wake-up sweep — same inline pattern as escalation.
        // Without this, conversations with inbox_status='snoozed' AND
        // snoozed_until <= now stay snoozed forever (no schedule:run
        // dependency in this codebase). Capped + cache-gated to once
        // per 30s per workspace so even a flood of due rows won't
        // dominate the queue() response. Broadcasts ConversationUpdated
        // so the kanban moves the card off the Snoozed column without
        // requiring an explicit refresh.
        $wakeCacheKey = "inbox:wake-sweep:{$wsId}";
        if (!cache()->has($wakeCacheKey)) {
            cache()->put($wakeCacheKey, 1, now()->addSeconds(30));
            try {
                $waking = Conversation::query()
                    ->forWorkspace($wsId)
                    ->where('inbox_status', 'snoozed')
                    ->whereNotNull('snoozed_until')
                    ->where('snoozed_until', '<=', now())
                    ->limit(50)
                    ->get();
                foreach ($waking as $c) {
                    $c->forceFill([
                        'inbox_status'  => 'open',
                        'snoozed_until' => null,
                    ])->save();
                    \App\Events\Inbox\ConversationUpdated::dispatch(
                        $c->id, $c->workspace_id, 'unsnoozed', ['inbox_status' => 'open']
                    );
                    \App\Models\ConversationEvent::record(
                        $c->id, (int) $c->workspace_id, null,
                        'auto_unsnoozed', ['by' => 'system'], 'system'
                    );
                    // Remind the assignee/team so the follow-up isn't missed.
                    try { app(\App\Services\Inbox\NotificationDispatcher::class)->notifySnoozeWake($c); }
                    catch (\Throwable $e) { /* notification is best-effort */ }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[INBOX] snooze wake sweep failed: ' . $e->getMessage());
            }
        }

        // Deal task reminders — same inline, no-scheduler pattern. Tasks with
        // a past due_at nudge the deal owner once (DealReminderService stamps
        // reminded_at). Cache-gated to once per 60s per workspace so it never
        // dominates the poll. Best-effort: a failure never breaks the queue.
        $dealRemindKey = "deals:remind-sweep:{$wsId}";
        if (!cache()->has($dealRemindKey)) {
            cache()->put($dealRemindKey, 1, now()->addSeconds(60));
            try { app(\App\Services\Deals\DealReminderService::class)->sweep($wsId, 100); }
            catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('[DEAL] task reminder sweep failed: ' . $e->getMessage()); }
        }

        $tab    = (string) $request->query('tab', 'mine');
        $status = (string) $request->query('status', 'open');
        $teamId = $request->query('team_id') ? (int) $request->query('team_id') : null;
        // Multi-device filter — narrows the queue to conversations
        // landed on a specific paired device. Workspace scoping below
        // already prevents an operator from filtering on another
        // workspace's device, so no extra ownership guard needed here.
        $deviceId = $request->query('device_id') ? (int) $request->query('device_id') : null;
        $search = trim((string) $request->query('q', ''));
        $page   = max(1, (int) $request->query('page', 1));
        $perPage = min(100, (int) $request->query('per_page', 30));

        // WhatsApp-style list controls.
        $archived = $request->boolean('archived');   // the "Archived" section
        $tagId    = $request->query('tag_id') ? (int) $request->query('tag_id') : null; // label filter
        // The Archived view ignores the open/mine funnels — it shows every
        // archived chat the operator may see (WhatsApp groups them together).
        if ($archived) { $tab = 'all'; $status = 'all'; }

        // Engine-aware: WABA workspace only sees WABA conversations,
        // Baileys only sees Baileys, etc. Without this, switching the
        // workspace's primary engine leaves stale conversations from
        // the old engine cluttering the queue (they'd still try to
        // reply through the new engine, which would fail).
        $q = Conversation::query()->forWorkspace($wsId)->forCurrentEngine()->deviceAlive();
        if ($deviceId) {
            $q->where('device_id', $deviceId);
        }

        $q = match ($status) {
            'all'      => $q,
            'open'     => $q->open(),
            'snoozed'  => $q->withInboxStatus('snoozed'),
            'resolved' => $q->withInboxStatus('resolved'),
            'closed'   => $q->withInboxStatus('closed'),
            'spam'     => $q->withInboxStatus('spam'),
            default    => $q->open(),
        };

        $q = match ($tab) {
            'mine'        => $q->assignedTo($user->id),
            'unassigned'  => $q->unassigned(),
            'mentions'    => $q->whereHas('participants', fn ($w) => $w
                ->where('user_id', $user->id)
                ->where('unread_mentions', '>', 0)),
            'sla_breach'  => $q->slaBreached(),
            'all'         => WorkspacePermissions::userCan($user, 'inbox.view_all_teams', $wsId)
                                ? $q
                                : $q->where(function ($w) use ($user) {
                                    $w->where('assignee_user_id', $user->id)
                                      ->orWhereIn('assignee_team_id',
                                          $user->teamsInWorkspace($user->current_workspace_id)->pluck('teams.id'));
                                  }),
            default       => $q->assignedTo($user->id),
        };

        if ($teamId) $q->forTeam($teamId);

        // Active vs Archived split — the default list hides archived chats
        // (like WhatsApp); the Archived section shows only archived ones.
        $q->where('archived', $archived);
        // Label filter — narrow the list to one tag/label.
        if ($tagId) {
            $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tagId));
        }

        // Hydrate (so encrypted title/preview decrypts) — same pattern as
        // the existing chat list. Ciphertext can't be LIKE-searched.
        // Pinned rows sort ABOVE everything else, then most-recent-first.
        $items = $q->orderByRaw('pinned_at IS NULL')
            ->orderByDesc('pinned_at')
            ->orderByDesc('last_message_at')->orderByDesc('id')
            ->with(['assignee:id,name', 'team:id,name,color', 'tags:id,name,color'])
            ->limit($perPage * 4)->get();

        if ($search !== '') {
            $items = Conversation::filterBySearch($items, $search);
        }

        $paged = $items->slice(($page - 1) * $perPage, $perPage)->values();

        // Pre-load all agents referenced by this page to avoid N+1 queries.
        $agentIds = $paged->pluck('assignee_agent_id')->filter()->unique()->values()->all();
        $agentMap = $agentIds ? AiAgent::whereIn('id', $agentIds)->get()->keyBy('id')->all() : [];

        // Same pattern for devices — one bulk Device::whereIn() is far
        // cheaper than a per-row find() when an agent has 30 rows
        // spanning multiple paired numbers.
        $deviceIds = $paged->pluck('device_id')->filter()->unique()->values()->all();
        $deviceMap = $deviceIds
            ? \App\Models\Device::whereIn('id', $deviceIds)->get()->keyBy('id')->all()
            : [];

        return response()->json([
            'items' => $paged->map(fn (Conversation $c) => $this->serializeListItem($c, $agentMap, $deviceMap)),
            'total' => $items->count(),
            'page'  => $page,
            'per_page' => $perPage,
            'counts' => $this->queueCounts($user, $wsId, $teamId, $deviceId),
            // Badge for the "Archived" row (engine-scoped, workspace-wide).
            'archived_count' => Conversation::query()->forWorkspace($wsId)
                ->forCurrentEngine()->deviceAlive()->where('archived', true)->count(),
        ]);
    }

    private function queueCounts(User $user, int $wsId, ?int $teamId = null, ?int $deviceId = null): array
    {
        // Mirror the queue() filter so badge counts match the list — including
        // the SAME team + device filters the list applies. Without this the
        // badges show workspace-wide totals ("All 32") even when a team with
        // zero conversations is selected, which reads as broken.
        $base = Conversation::query()->forWorkspace($wsId)->forCurrentEngine()->deviceAlive()->open();
        if ($teamId)   $base->forTeam($teamId);
        if ($deviceId) $base->where('device_id', $deviceId);
        $teamIds = $user->teamsInWorkspace($wsId)->pluck('teams.id');
        $canSeeAll = WorkspacePermissions::userCan($user, 'inbox.view_all_teams', $wsId);

        // 'all' count must mirror the same membership filter the 'all' tab
        // query applies — otherwise the badge says "5" but the operator
        // only sees 2 rows in the list, which looks broken. Owners/admins
        // (inbox.view_all_teams) see the true workspace-wide total.
        $allQuery = (clone $base);
        if (!$canSeeAll) {
            $allQuery->where(function ($w) use ($user, $teamIds) {
                $w->where('assignee_user_id', $user->id)
                  ->orWhereIn('assignee_team_id', $teamIds);
            });
        }

        return [
            'mine'        => (clone $base)->assignedTo($user->id)->count(),
            'unassigned'  => (clone $base)->unassigned()->count(),
            'mentions'    => ConversationParticipant::where('user_id', $user->id)
                ->where('workspace_id', $wsId)
                ->where('unread_mentions', '>', 0)->count(),
            'sla_breach'  => (clone $base)->where('sla_breached', true)->count(),
            'all'         => $allQuery->count(),
        ];
    }

    // ----------------------------------------------------------------
    // Compose — start a BRAND-NEW outbound conversation to many
    // recipients at once (saved contacts + contact groups + manual
    // numbers) from a chosen channel. Reuses the find-or-create-
    // conversation + InboxDispatcher path Quick Send uses, so every send
    // lands in the Team Inbox and is plan-gated + wallet-charged.
    // ----------------------------------------------------------------

    /** Options the compose panel needs: channels, contacts, groups. */
    public function composeOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);

        $channels = \App\Services\WorkspaceEngine::senders($wsId)
            ->map(fn ($s) => [
                'value'  => $s['key'],
                'label'  => $s['label'],
                'phone'  => $s['phone'],
                'engine' => $s['descriptor']['label'] ?? ($s['engine'] ?? ''),
            ])->values();

        $contacts = \App\Models\Contact::query()->forCurrentWorkspace()
            ->orderByDesc('id')->limit(3000)->get()
            ->map(function ($c) {
                if ($c->is_unsubscribed) return null;
                $phone = \App\Models\Contact::canonicalizePhone($c->country_code ?? null, $c->mobile);
                return strlen($phone) < 8 ? null : [
                    'id'     => (int) $c->id,
                    'name'   => (string) ($c->name ?: $phone),
                    'phone'  => $phone,
                    'groups' => is_array($c->contact_group) ? array_map('intval', $c->contact_group) : [],
                ];
            })->filter()->values();

        // contact_groups name column is `user_group` and it's ENCRYPTED, so
        // it can't be SQL-ordered (ciphertext) — decrypt + sort in PHP.
        $groups = \App\Models\ContactGroup::query()->forCurrentWorkspace()->get()
            ->map(fn ($g) => ['id' => (int) $g->id, 'name' => (string) $g->user_group])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['channels' => $channels, 'contacts' => $contacts, 'groups' => $groups]);
    }

    /** Send a new message to every resolved recipient from the chosen channel. */
    public function composeSend(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) ($user?->current_workspace_id ?? 0);
        if (!$wsId) {
            return response()->json(['ok' => false, 'error' => 'no_workspace'], 422);
        }

        $data = $request->validate([
            'channel'      => 'required|string|max:40',
            'body'         => 'nullable|string|max:4096',
            'template_id'  => 'nullable|integer|exists:wa_templates,id',
            'contact_ids'  => 'nullable|array',
            'contact_ids.*'=> 'integer',
            'group_ids'    => 'nullable|array',
            'group_ids.*'  => 'integer',
            'numbers'      => 'nullable|string|max:20000',
        ]);

        $parsed = \App\Services\WorkspaceEngine::parseSenderKey($wsId, $data['channel']);
        if (!$parsed) {
            return response()->json(['ok' => false, 'error' => 'bad_channel'], 422);
        }
        $engine   = (string) $parsed['engine'];
        $deviceId = (int) $parsed['id'];
        $from     = '';
        if ($engine === \App\Services\WorkspaceEngine::ENGINE_BAILEYS) {
            $dev = \App\Models\Device::query()->where('id', $deviceId)->where('workspace_id', $wsId)->first();
            $from = $dev ? preg_replace('/\D+/', '', (string) ($dev->country_code . $dev->phone_number)) : '';
        } else {
            $cfg = \App\Models\WaProviderConfig::query()->where('id', $deviceId)->where('workspace_id', $wsId)->first();
            $from = $cfg ? preg_replace('/\D+/', '', (string) $cfg->phone_number) : '';
        }

        $body = (string) ($data['body'] ?? '');
        $templateMeta = null;
        if (!empty($data['template_id'])) {
            $tpl = \App\Models\WaTemplate::query()->forCurrentWorkspace()->find($data['template_id']);
            if ($tpl) {
                $body = (string) $tpl->template_body;
                $templateMeta = array_filter([
                    'header'            => (string) ($tpl->header ?: ''),
                    'footer'            => (string) ($tpl->footer ?: ''),
                    'buttons'           => is_array($tpl->buttons) ? $tpl->buttons : [],
                    'template_id'       => (int) $tpl->id,
                    'template_name'     => (string) ($tpl->template_name ?? ''),
                    'template_language' => (string) ($tpl->language ?? 'en'),
                ], fn ($v) => $v !== '' && $v !== []);
            }
        }
        if (trim($body) === '') {
            return response()->json(['ok' => false, 'error' => 'empty_body'], 422);
        }

        $phones = $this->resolveComposeRecipients(
            $data['contact_ids'] ?? [],
            $data['group_ids'] ?? [],
            (string) ($data['numbers'] ?? ''),
        );
        if ($phones->isEmpty()) {
            return response()->json(['ok' => false, 'error' => 'no_recipients'], 422);
        }
        $capped = $phones->count() > 500;
        $phones = $phones->take(500);

        $sent = 0; $failed = 0; $errors = [];
        foreach ($phones as $to) {
            try {
                $ok = $this->deliverComposed($user, $wsId, $engine, $deviceId ?: null, $from, $to, $body, $templateMeta);
                $ok ? $sent++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                if (count($errors) < 5) $errors[] = mb_substr($e->getMessage(), 0, 120);
            }
        }

        AuditLogger::workspace('conversation.compose', $user->id, $wsId, 'workspace', $wsId, [
            'sent' => $sent, 'failed' => $failed, 'engine' => $engine,
        ]);

        return response()->json([
            'ok'      => $sent > 0,
            'sent'    => $sent,
            'failed'  => $failed,
            'total'   => $phones->count(),
            'capped'  => $capped,
            'errors'  => $errors,
        ]);
    }

    /**
     * Resolve the unique recipient phone set for a compose send —
     * selected contacts + every member of the selected groups + manual
     * numbers, deduped. Encrypted `mobile` / `contact_group` can't be
     * SQL-filtered, so contacts are matched in PHP. Extracted so it can be
     * unit-tested WITHOUT dispatching a real send.
     */
    private function resolveComposeRecipients(array $contactIds, array $groupIds, string $numbers): \Illuminate\Support\Collection
    {
        $phones = collect();
        $wantContactIds = collect($contactIds)->map(fn ($i) => (int) $i);
        $wantGroupIds   = collect($groupIds)->map(fn ($i) => (int) $i);

        if ($wantContactIds->isNotEmpty() || $wantGroupIds->isNotEmpty()) {
            \App\Models\Contact::query()->forCurrentWorkspace()->limit(5000)->get()
                ->each(function ($c) use (&$phones, $wantContactIds, $wantGroupIds) {
                    if ($c->is_unsubscribed) return;
                    $inGroup = $wantGroupIds->isNotEmpty()
                        && is_array($c->contact_group)
                        && count(array_intersect(array_map('intval', $c->contact_group), $wantGroupIds->all())) > 0;
                    if (!$wantContactIds->contains((int) $c->id) && !$inGroup) return;
                    $p = \App\Models\Contact::canonicalizePhone($c->country_code ?? null, $c->mobile);
                    if (strlen($p) >= 8) $phones->push($p);
                });
        }

        // Manual numbers — split on comma / semicolon / newline only (NOT
        // spaces), so a number typed with internal spacing like
        // "+91 94444 44444" stays one token and isn't shredded into
        // sub-8-digit fragments; each token is then reduced to digits.
        foreach (preg_split('/[,;\r\n]+/', $numbers) as $raw) {
            $p = preg_replace('/\D+/', '', (string) $raw);
            if (strlen($p) >= 8) $phones->push($p);
        }

        return $phones->unique()->values();
    }

    /**
     * Send one composed message to a single phone — find-or-create the
     * conversation on the same key the inbound webhook + Quick Send use,
     * create the outbound InboxMessage, dispatch, and stamp the outcome.
     * Returns true on a successful send.
     */
    private function deliverComposed(User $user, int $wsId, string $engine, ?int $deviceId, string $from, string $to, string $body, ?array $templateMeta): bool
    {
        $toJid = str_contains($to, '@') ? $to : $to . '@s.whatsapp.net';

        $conv = Conversation::query()
            ->where('workspace_id', $wsId)
            ->where('provider', $engine)
            ->whereIn('origin', ['inbox', 'chatbot'])
            ->where(fn ($q) => $q->whereIn('raw_jid', [$toJid, $to])->orWhereIn('alt_jid', [$toJid, $to]))
            ->orderByDesc('id')
            ->first();

        if (!$conv) {
            $conv = Conversation::create([
                'user_id'          => $user->id,
                'workspace_id'     => $wsId,
                'device_id'        => $deviceId,
                'title'            => $to,
                'preview'          => mb_substr($body, 0, 200),
                'status'           => 'pending',
                'platform'         => 'W',
                'provider'         => $engine,
                'origin'           => 'inbox',
                'raw_jid'          => $toJid,
                'recipients_count' => 1,
                'last_message_at'  => now(),
            ]);
        } else {
            $patch = ['preview' => mb_substr($body, 0, 200), 'last_message_at' => now()];
            $staleDevice = $conv->device_id && !\App\Models\Device::whereKey($conv->device_id)->exists();
            if ($deviceId && (!$conv->device_id || $staleDevice)) $patch['device_id'] = $deviceId;
            if (in_array((string) $conv->inbox_status, ['closed', 'resolved', 'spam'], true)) $patch['inbox_status'] = 'open';
            if ($conv->raw_jid === $to && $toJid !== $to) $patch['raw_jid'] = $toJid;
            $conv->update($patch);
        }

        $meta = ['source' => 'compose', 'target_jid' => $to];
        if ($templateMeta) $meta = array_merge($meta, $templateMeta);

        $msg = InboxMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $user->id,
            'direction'       => 'out',
            'from_number'     => $from ?: null,
            'to_number'       => $to,
            'body'            => $body,
            'status'          => 'pending',
            'meta'            => $meta,
        ]);

        $result = app(\App\Services\InboxDispatcher::class)->send($msg, $conv->platform ?? 'W');

        if (($result['ok'] ?? false) === true) {
            $fields = ['status' => 'sent', 'sent_at' => now()];
            if (!empty($result['provider_id'])) {
                $fields['meta'] = array_merge(is_array($msg->meta) ? $msg->meta : [], ['wa_message_id' => (string) $result['provider_id']]);
            }
            $msg->update($fields);
            $conv->forceFill(['last_message_at' => now(), 'last_outbound_at' => now(), 'preview' => mb_substr($body, 0, 200)])->save();
            return true;
        }

        $err = (string) ($result['error'] ?? $result['message'] ?? 'Send failed.');
        $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($err, 0, 190)]);
        return false;
    }

    // ----------------------------------------------------------------
    // Conversation detail — open from the queue.
    // ----------------------------------------------------------------

    /**
     * Heartbeat for collision detection. Client posts here every 10s
     * while a conversation is open AND on every keystroke (with
     * `typing=1`). Returns the current viewers/typists snapshot so the
     * UI can paint avatars + "X is typing" without a second round-trip.
     * Self is filtered out of the response — caller doesn't see their
     * own avatar.
     */
    public function presencePing(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('view', $conv);

        $user   = $request->user();
        $typing = $request->boolean('typing');
        $svc    = app(\App\Services\Inbox\PresenceService::class);

        $svc->ping(
            conversationId: (int) $conv->id,
            userId: (int) $user->id,
            name: (string) ($user->name ?: 'User #' . $user->id),
            avatar: (string) ($user->avatar_url ?? null),
            typing: $typing,
        );

        return response()->json([
            'ok'       => true,
            'presence' => $svc->snapshot((int) $conv->id, (int) $user->id),
        ]);
    }

    /**
     * Fired by the client on conversation close + window beforeunload
     * so the viewer drops out of the snapshot immediately instead of
     * lingering until VIEWING_TTL (30s) elapses. Best-effort —
     * sendBeacon-friendly so it survives tab close.
     */
    public function presenceLeave(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('view', $conv);

        $user = $request->user();
        app(\App\Services\Inbox\PresenceService::class)->leave(
            (int) $conv->id,
            (int) $user->id,
        );
        return response()->json(['ok' => true]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('view', $conv);

        $user = $request->user();
        // Pagination: when `before` is set we're loading OLDER messages
        // beyond the initial 80, so we narrow to messages whose id is
        // less than the oldest one the client already has. This powers
        // infinite-scroll-up in the thread UI without burning memory on
        // multi-thousand-message threads.
        $before = (int) $request->query('before', 0);
        // The inboxMessages() relation carries a default orderBy('created_at')
        // ASC; chaining ->latest() (created_at DESC) leaves BOTH clauses, so the
        // LIMIT 80 does NOT reliably return the newest slice. On a thread with
        // >80 messages the newest ones fall OUTSIDE the window and the chat area
        // freezes on old messages (visible bug: conv 12 had 142 msgs but only
        // loaded up to id 141). reorder() drops the relation's default order;
        // sort by id DESC (monotonic — tie/timezone-proof) to always get the
        // newest 80, then reverse to oldest→newest for display.
        $msgQuery = $conv->inboxMessages()->reorder()->orderByDesc('id');
        if ($before > 0) $msgQuery->where('id', '<', $before);
        $messages = $msgQuery->limit(80)->get()->reverse()->values();

        // DIAGNOSTIC — why do messages "not show" on one specific number?
        // Compares what the OPEN thread actually loads vs what exists on the
        // conversation row. If total_on_conv > returned_newest_id (or the newest
        // returned message is old while total keeps growing) the thread is
        // opening the WRONG conversation id for that contact (a duplicate row) —
        // stores land on conv A, the UI reads conv B. If total == returned and
        // newest_id is current, the server IS returning them → it's a client
        // render / realtime issue, not the query.
        try {
            $newest = $messages->last();
            \Log::info('[TEAM-INBOX SHOW] thread load', [
                'req_conv_id'   => $id,
                'conv_id'       => $conv->id,
                'device_id'     => $conv->device_id,
                'provider'      => $conv->provider ?? null,
                'workspace_id'  => $conv->workspace_id,
                'inbox_status'  => $conv->inbox_status ?? null,
                'raw_jid'       => $conv->raw_jid,
                'before'        => $before,
                'total_on_conv' => $conv->inboxMessages()->count(),
                'returned'      => $messages->count(),
                'newest_id'     => optional($newest)->id,
                'newest_dir'    => optional($newest)->direction,
                'newest_at'     => optional(optional($newest)->created_at)->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[TEAM-INBOX SHOW] diag log failed: ' . $e->getMessage());
        }

        // For pagination requests, only return the messages slice — the
        // client already has notes/events/tags/history loaded and doesn't
        // need them refetched.
        if ($before > 0) {
            $msgAgentIds = $messages->pluck('agent_id')->filter()->unique()->values()->all();
            $msgAgentMap = $msgAgentIds ? AiAgent::whereIn('id', $msgAgentIds)->get()->keyBy('id')->all() : [];
            return response()->json([
                'messages' => $messages->map(fn (InboxMessage $m) => $this->serializeMessage($m, $msgAgentMap)),
                'has_more' => $messages->count() >= 80,
            ]);
        }

        $notes = $conv->notes()->limit(80)->get();
        $events = $conv->events()->limit(80)->get();
        $tags = $conv->tags()->get(['tags.id','tags.name','tags.color','tags.slug']);

        // Past conversations with the same contact — match by canonical
        // JID (raw_jid or alt_jid). Drop the current conversation so
        // it doesn't show as a "past" thread of itself.
        $history = collect();
        $jids = array_values(array_filter([$conv->raw_jid, $conv->alt_jid]));
        if (!empty($jids)) {
            // Workspace-shared history — surface every teammate's past
            // thread with this contact, not just rows the current user
            // happens to own.
            $history = Conversation::query()
                ->where('workspace_id', $conv->workspace_id)
                ->where('device_id', $conv->device_id)
                ->where('origin', 'chat')
                ->where('id', '!=', $conv->id)
                ->where(function ($q) use ($jids) {
                    $q->whereIn('raw_jid', $jids)->orWhereIn('alt_jid', $jids);
                })
                ->orderByDesc('last_message_at')
                ->limit(20)
                ->get(['id', 'title', 'preview', 'last_message_at', 'inbox_status', 'resolved_at'])
                ->map(fn ($h) => [
                    'id'              => $h->id,
                    'title'           => mask_phone((string) $h->title),
                    'preview'         => (string) $h->preview,
                    'last_message_at' => $h->last_message_at,
                    'inbox_status'    => $h->inbox_status,
                    'resolved_at'     => $h->resolved_at,
                ])
                ->values();
        }

        // Mark this user's participant row read.
        $part = ConversationParticipant::firstOrCreate(
            ['conversation_id' => $conv->id, 'user_id' => $user->id],
            ['workspace_id' => $conv->workspace_id, 'role' => 'watcher'],
        );
        $part->forceFill([
            'last_read_at'    => now(),
            'unread_messages' => 0,
            'unread_mentions' => 0,
        ])->save();

        // Pre-load agents referenced by messages to avoid N+1.
        $msgAgentIds = $messages->pluck('agent_id')->filter()->unique()->values()->all();
        $msgAgentMap = $msgAgentIds ? AiAgent::whereIn('id', $msgAgentIds)->get()->keyBy('id')->all() : [];

        // has_more: signal to the client whether older messages exist
        // beyond the loaded 80, so the infinite-scroll handler knows
        // whether to keep firing pagination requests on scroll-up.
        $totalMessages = $conv->inboxMessages()->count();
        $hasMore = $totalMessages > $messages->count();

        // Customer profile sidebar — surface the matching Contact row
        // (email, address, custom attributes) and the customer's
        // commerce order history. Both lookups use the canonical JID
        // (or raw_jid digits) so the conversation pane shows full CRM
        // context without leaving the inbox.
        $contactProfile = null;
        $orders = collect();
        try {
            $contactProfile = \App\Models\Contact::query()
                ->where('workspace_id', $conv->workspace_id)
                ->get()
                ->first(function ($c) use ($conv) {
                    $digits = preg_replace('/\D+/', '', (string) ($c->mobile ?: ''));
                    return $digits !== '' && ($digits === $conv->raw_jid || $digits === $conv->alt_jid);
                });
            if ($contactProfile) {
                $contactProfile = [
                    'id'                => $contactProfile->id,
                    'name'              => (string) ($contactProfile->name ?: ''),
                    'email'             => (string) ($contactProfile->email ?: ''),
                    'mobile'            => mask_phone((string) ($contactProfile->mobile ?: '')),
                    'country_code'      => (string) ($contactProfile->country_code ?: ''),
                    'address'           => (string) ($contactProfile->address ?: ''),
                    'language'          => (string) ($contactProfile->language ?: ''),
                    'image'             => $contactProfile->image
                        ? (\Illuminate\Support\Str::startsWith($contactProfile->image, ['http://', 'https://']) ? $contactProfile->image : media_url($contactProfile->image))
                        : null,
                    'custom_attributes' => is_array($contactProfile->custom_attributes) ? $contactProfile->custom_attributes : [],
                    'is_unsubscribed'   => (bool) $contactProfile->is_unsubscribed,
                ];
            }
        } catch (\Throwable $e) { /* contact lookup non-fatal */ }

        try {
            $orders = \App\Models\WaOrder::query()
                ->where('workspace_id', $conv->workspace_id)
                ->where('customer_phone', $conv->raw_jid)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'source', 'customer_name', 'items_json', 'total_minor', 'currency_code', 'status', 'created_at'])
                ->map(fn ($o) => [
                    'id'             => $o->id,
                    'source'         => $o->source,
                    'customer_name'  => $o->customer_name,
                    'items_count'    => is_array($o->items_json) ? count($o->items_json) : 0,
                    'total_minor'    => (int) $o->total_minor,
                    'currency_code'  => $o->currency_code,
                    'status'         => $o->status,
                    'created_at'     => $o->created_at?->toIso8601String(),
                ]);
        } catch (\Throwable $e) { /* orders non-fatal */ }

        return response()->json([
            'conversation'    => $this->serializeFull($conv, $tags),
            'messages'        => $messages->map(fn (InboxMessage $m) => $this->serializeMessage($m, $msgAgentMap)),
            'notes'           => $notes->map(fn (ConversationNote $n) => $this->serializeNote($n)),
            'events'          => $events->map(fn (ConversationEvent $e) => $this->serializeEvent($e)),
            'history'         => $history,
            'contact_profile' => $contactProfile,
            'orders'          => $orders,
            'has_more'        => $hasMore,
        ]);
    }

    // (Forward already implemented at messageForward() further down —
    //  the duplicate stub here was removed 2026-05-27 audit closeout.
    //  Canonical implementation handles wallet charge + target_jid
    //  resolution that this simpler version lacked.)

    // ----------------------------------------------------------------
    // Mutations
    // ----------------------------------------------------------------

    public function assign(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('assign', $conv);

        $data = $request->validate([
            'user_id'  => 'nullable|integer',
            'team_id'  => 'nullable|integer',
            'strategy' => 'nullable|in:manual,round_robin,least_loaded,sticky',
        ]);

        $previousUserId = $conv->assignee_user_id;
        $strategy = $data['strategy'] ?? 'manual';
        $resolved = $this->assignment->assign($conv, $data['user_id'] ?? null, $data['team_id'] ?? null, $strategy, $request->user()->id);

        if ($resolved) {
            $this->notify->notifyAssignment($conv->fresh(), $resolved->id, $request->user()->id);
        }
        AuditLogger::workspace('conversation.assigned', $request->user()->id, $conv->workspace_id, 'conversation', $conv->id, [
            'to_user_id' => $resolved?->id, 'team_id' => $data['team_id'] ?? null, 'strategy' => $strategy,
        ]);
        broadcast(ConversationAssigned::fromModel($conv->fresh(), $previousUserId, $request->user()->id))->toOthers();
        app(\App\Services\Inbox\OutboundWebhookDispatcher::class)->fire('conversation.assigned', $conv->fresh(), [
            'to_user_id' => $resolved?->id,
            'team_id'    => $data['team_id'] ?? null,
            'strategy'   => $strategy,
            'by_user_id' => $request->user()->id,
        ]);

        return response()->json(['ok' => true, 'conversation' => $this->serializeListItem($conv->fresh())]);
    }

    public function unassign(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('assign', $conv);

        $previousUserId = $conv->assignee_user_id;
        $this->assignment->unassign($conv, $request->user()->id);
        broadcast(ConversationAssigned::fromModel($conv->fresh(), $previousUserId, $request->user()->id))->toOthers();
        AuditLogger::workspace('conversation.unassigned', $request->user()->id, $conv->workspace_id, 'conversation', $conv->id);

        return response()->json(['ok' => true, 'conversation' => $this->serializeListItem($conv->fresh())]);
    }

    /**
     * POST /team-inbox/api/conversations/{id}/assign-assistant
     * Hooks a workspace AI Voice Assistant into the conversation. The
     * AiCallAssistant id lands in `routing_meta.voice_assistant_id`; the
     * inbound message handler picks it up on the next customer reply
     * and runs AiAgentService::respondAsVoiceAssistant. Pass
     * assistant_id=0 to unassign.
     */
    public function assignAssistant(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('assign', $conv);
        $data = $request->validate([
            'assistant_id' => 'nullable|integer',
        ]);

        $aId = (int) ($data['assistant_id'] ?? 0);
        $assistant = null;
        if ($aId > 0) {
            $assistant = \App\Models\AiCallAssistant::where('workspace_id', $conv->workspace_id)->find($aId);
            if (!$assistant) {
                return response()->json(['ok' => false, 'error' => 'assistant_not_found'], 404);
            }
        }

        $meta = $conv->routing_meta ?? [];
        if ($aId > 0) {
            $meta['voice_assistant_id']   = $aId;
            $meta['voice_assistant_name'] = $assistant->name;
            $meta['voice_assistant_at']   = now()->toIso8601String();
            $conv->forceFill(['routing_meta' => $meta])->save();
            // Attaching an AI to a live chat must STOP any running flow session
            // (Baileys/Node), or the flow and the AI both reply to the customer.
            $this->endActiveFlowSession($conv);
        } else {
            // DETACH = stop ALL AI on this conversation. There are TWO
            // independent AI-reply triggers and different inbound paths read
            // different ones:
            //   • routing_meta.voice_assistant_id → respondAsVoiceAssistant
            //     (read by the Baileys inbound path)
            //   • assignee_agent_id → respondIfAssigned
            //     (read by BOTH Baileys AND WABA inbound)
            // The "Detach AI" button used to clear ONLY voice_assistant_id, so
            // on a WABA workspace the AI Agent (assignee_agent_id) kept
            // replying — the operator hit Detach and it did nothing. Clear
            // BOTH so Detach means Detach on every engine.
            unset($meta['voice_assistant_id'], $meta['voice_assistant_name'], $meta['voice_assistant_at']);
            $conv->forceFill([
                'routing_meta'      => $meta,
                'assignee_agent_id' => null,
            ])->save();
        }

        ConversationEvent::record(
            $conv->id, $conv->workspace_id, $request->user()->id,
            $aId > 0 ? 'voice_assistant_attached' : 'voice_assistant_detached',
            ['assistant_id' => $aId, 'assistant_name' => $assistant?->name],
        );

        return response()->json([
            'ok' => true,
            'voice_assistant_id'   => $aId > 0 ? $aId : null,
            'voice_assistant_name' => $assistant?->name,
            // Tell the app the agent slot was cleared too, so its UI can
            // reflect "no AI attached" without a refetch.
            'assignee_agent_id'    => $aId > 0 ? $conv->assignee_agent_id : null,
        ]);
    }

    /**
     * Tell the Baileys/Node runtime to END any active flow session for this
     * conversation's customer, so a manually-attached AI agent/assistant
     * cleanly takes over instead of the flow AND the AI both replying to the
     * customer. The flow session lives in Node memory (activeFlowSessions),
     * keyed by the customer phone → we POST the phone to Node /api/flow-end.
     * Best-effort: a Node/network hiccup never blocks the assignment. Only
     * meaningful for the Unofficial (Baileys) engine; WABA/Twilio flows aren't
     * sessioned here, and Node just returns ended:0.
     */
    private function endActiveFlowSession(Conversation $conv): void
    {
        try {
            $phone = preg_replace('/\D+/', '', (string) ($conv->raw_jid ?: ''));
            if ($phone === '' && $conv->contact_id) {
                $phone = preg_replace('/\D+/', '', (string) (\App\Models\Contact::query()
                    ->whereKey($conv->contact_id)->value('phone') ?? ''));
            }
            if ($phone === '') return;

            $serverUrl = '';
            $cfg = \App\Models\WaProviderConfig::query()->primaryForWorkspace($conv->workspace_id)->first();
            if ($cfg) $serverUrl = (string) ($cfg->creds()['server_url'] ?? '');
            if ($serverUrl === '') {
                $serverUrl = (string) (\App\Models\SystemSetting::get('baileys_server_url') ?: env('SERVER_URL', ''));
            }
            if ($serverUrl === '') return;

            \Illuminate\Support\Facades\Http::timeout(5)
                ->withHeaders(['X-Node-Token' => (string) node_token()])
                ->post(rtrim($serverUrl, '/') . '/api/flow-end', [
                    'workspace_id'   => (int) $conv->workspace_id,
                    'customer_phone' => $phone,
                ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[TEAM-INBOX] flow-end call failed: ' . $e->getMessage());
        }
    }

    /**
     * GET /team-inbox/api/assistants
     * Returns the workspace's live AI Voice Assistants — used to
     * populate the "Hand over to AI" picker in the conversation header.
     */
    public function listAssistants(Request $request): JsonResponse
    {
        $wsId = (int) $request->user()->current_workspace_id;
        $rows = \App\Models\AiCallAssistant::query()
            ->where('workspace_id', $wsId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'ai_provider', 'ai_model', 'status'])
            ->map(fn ($a) => [
                'id'       => $a->id,
                'name'     => $a->name,
                'provider' => $a->ai_provider,
                'model'    => $a->ai_model,
                'status'   => $a->status,
            ]);
        return response()->json(['ok' => true, 'assistants' => $rows]);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('resolve', $conv);

        $agentId   = $conv->assignee_agent_id;
        $agentName = $agentId ? optional(AiAgent::find($agentId))->name : null;

        $conv->forceFill([
            'inbox_status'          => 'resolved',
            'resolved_at'           => now(),
            'resolved_by'           => $request->user()->id,
            'resolved_by_agent_id'  => $agentId,
        ])->save();

        $this->sla->applyOnResolution($conv);
        ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'resolved', [
            'agent_id'   => $agentId,
            'agent_name' => $agentName,
            'by'         => $request->user()->name,
        ]);
        $this->notify->notifyResolved($conv, $request->user()->id);
        AuditLogger::workspace('conversation.resolved', $request->user()->id, $conv->workspace_id, 'conversation', $conv->id);
        AgentStatus::where('user_id', $request->user()->id)
            ->where('workspace_id', $conv->workspace_id)
            ->increment('today_resolutions');
        broadcast(ConversationUpdated::fromModel($conv->fresh(), 'resolved'))->toOthers();
        app(\App\Services\Inbox\OutboundWebhookDispatcher::class)->fire('conversation.resolved', $conv->fresh(), [
            'resolved_by_user_id'  => $request->user()->id,
            'resolved_by_agent_id' => $agentId,
            'resolved_at'          => now()->toIso8601String(),
        ]);

        return response()->json(['ok' => true, 'conversation' => $this->serializeListItem($conv->fresh())]);
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('resolve', $conv);

        $conv->forceFill(['inbox_status' => 'open', 'resolved_at' => null, 'resolved_by' => null])->save();
        ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'reopened');
        AuditLogger::workspace('conversation.reopened', $request->user()->id, $conv->workspace_id, 'conversation', $conv->id);
        broadcast(ConversationUpdated::fromModel($conv->fresh(), 'reopened'))->toOthers();

        return response()->json(['ok' => true, 'conversation' => $this->serializeListItem($conv->fresh())]);
    }

    public function snooze(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('snooze', $conv);

        $data = $request->validate(['until' => 'required|date|after:now']);
        $conv->forceFill(['inbox_status' => 'snoozed', 'snoozed_until' => $data['until']])->save();

        ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'snoozed', ['until' => $data['until']]);
        AuditLogger::workspace('conversation.snoozed', $request->user()->id, $conv->workspace_id, 'conversation', $conv->id, ['until' => $data['until']]);
        broadcast(ConversationUpdated::fromModel($conv->fresh(), 'snoozed', ['until' => $data['until']]))->toOthers();

        return response()->json(['ok' => true, 'conversation' => $this->serializeListItem($conv->fresh())]);
    }

    /**
     * POST /team-inbox/api/conversations/{id}/create-deal
     * "Create deal" button on the conversation header — opens a CRM deal
     * in the default pipeline, prefilled + linked to this contact and
     * conversation (the inbox → deal direction). Plan-gated.
     */
    public function createDeal(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $wsId = (int) $conv->workspace_id;

        $ws = $request->user()->currentWorkspace;
        if (!\App\Services\PlanLimitGuard::hasFeature($ws, 'access_sales_pipeline')) {
            return response()->json(['ok' => false, 'message' => 'Sales Pipeline is not enabled on your plan.'], 422);
        }

        $data = $request->validate([
            'title'    => 'nullable|string|max:191',
            'value'    => 'nullable|numeric|min:0|max:99999999',
            'stage_id' => 'nullable|integer',
        ]);

        $pipeline = \App\Models\Pipeline::ensureDefaultForWorkspace($wsId);
        // Requested stage (must belong to this pipeline) → first stage.
        $stage = null;
        if (!empty($data['stage_id'])) {
            $stage = $pipeline->stages()->find((int) $data['stage_id']);
        }
        $stage = $stage ?: $pipeline->stages()->orderBy('sort_order')->first();
        if (!$stage) {
            return response()->json(['ok' => false, 'message' => 'Your pipeline has no stages yet.'], 422);
        }

        // Link to the matching Contact via the same phone-digits match the
        // conversation sidebar uses, so the deal shows the right person.
        $contactId = null;
        $contactName = '';
        try {
            $contact = \App\Models\Contact::where('workspace_id', $wsId)->get()->first(function ($c) use ($conv) {
                $digits = preg_replace('/\D+/', '', (string) ($c->mobile ?: ''));
                return $digits !== '' && ($digits === $conv->raw_jid || $digits === $conv->alt_jid);
            });
            if ($contact) { $contactId = $contact->id; $contactName = (string) $contact->name; }
        } catch (\Throwable $e) { /* non-fatal */ }

        $title = trim((string) ($data['title'] ?? ''))
            ?: ($contactName ?: ('Deal — ' . mask_phone((string) $conv->raw_jid)));

        $deal = \App\Models\Deal::create([
            'workspace_id'    => $wsId,
            'pipeline_id'     => $pipeline->id,
            'stage_id'        => $stage->id,
            'contact_id'      => $contactId,
            'conversation_id' => $conv->id,
            'title'           => mb_substr($title, 0, 191),
            'value_minor'     => (int) round((float) ($data['value'] ?? 0) * 100),
            'currency'        => $pipeline->currency,
            'owner_user_id'   => (int) $request->user()->id,
            'source'          => 'inbox',
            'sort_order'      => 0,
        ]);

        ConversationEvent::record($conv->id, $wsId, $request->user()->id, 'note_added', [
            'note'   => 'Deal created: ' . $title . ' (#' . $deal->id . ')',
            'source' => 'deal',
        ], 'deal');

        return response()->json([
            'ok'      => true,
            'deal_id' => $deal->id,
            'url'     => url('/deals'),
            'message' => 'Deal created in ' . $pipeline->name . '.',
        ]);
    }

    /**
     * GET /conversations/{id}/deals — the Sales Pipeline deals tied to this
     * conversation OR to its contact, so an agent sees what's already in play
     * with this person WITHOUT leaving the chat (the conversation-centric CRM
     * view competitors like Kommo build their pitch around). Open deals first.
     */
    public function conversationDeals(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $wsId = (int) $conv->workspace_id;

        $ws = $request->user()->currentWorkspace;
        if (!\App\Services\PlanLimitGuard::hasFeature($ws, 'access_sales_pipeline')) {
            // Not an error — the panel just stays hidden when the plan lacks it.
            return response()->json(['ok' => true, 'enabled' => false, 'deals' => []]);
        }

        // Resolve the matching contact the same way createDeal does, so a deal
        // linked only by contact_id (e.g. an auto-deal from an order) still
        // surfaces here even if it predates this conversation.
        $contactId = null;
        try {
            $contact = \App\Models\Contact::where('workspace_id', $wsId)->get()->first(function ($c) use ($conv) {
                $digits = preg_replace('/\D+/', '', (string) ($c->mobile ?: ''));
                return $digits !== '' && ($digits === $conv->raw_jid || $digits === $conv->alt_jid);
            });
            if ($contact) $contactId = $contact->id;
        } catch (\Throwable $e) { /* non-fatal */ }

        $deals = \App\Models\Deal::query()
            ->where('workspace_id', $wsId)
            ->where(function ($q) use ($conv, $contactId) {
                $q->where('conversation_id', $conv->id);
                if ($contactId) $q->orWhere('contact_id', $contactId);
            })
            ->with(['stage:id,name', 'owner:id,name'])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return response()->json([
            'ok'      => true,
            'enabled' => true,
            'deals'   => $deals->map(fn ($d) => [
                'id'            => $d->id,
                'title'         => $d->title,
                'value_display' => $d->value_display,
                'status'        => $d->status,
                'stage_name'    => optional($d->stage)->name,
                'owner_name'    => $d->owner && $d->owner->id ? $d->owner->name : null,
                'url'           => url('/deals?deal=' . $d->id),
            ])->values(),
        ]);
    }

    public function priority(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('priority', $conv);

        $data = $request->validate(['priority' => 'required|in:' . implode(',', Conversation::PRIORITIES)]);
        $previous = $conv->priority;
        $conv->forceFill(['priority' => $data['priority']])->save();
        ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'priority_changed', ['from' => $previous, 'to' => $data['priority']]);
        broadcast(ConversationUpdated::fromModel($conv->fresh(), 'priority', ['priority' => $data['priority']]))->toOthers();

        return response()->json(['ok' => true]);
    }

    public function tag(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('tag', $conv);

        $data = $request->validate([
            'tag_id' => 'nullable|integer',
            'name'   => 'nullable|string|max:64',
            'color'  => 'nullable|string|max:16|regex:/^#?[0-9A-Za-z_-]{1,16}$/',
        ]);
        // Laravel's `nullable` rule doesn't auto-fill missing keys to null,
        // so a request that only sends `name` + `color` (which is how the
        // quick-label shortcut + Add-tag prompt do it) would trip the
        // Undefined-array-key fatal. Use the null-coalescing operator.
        $tagId = $data['tag_id'] ?? null;
        $tag = $tagId
            ? Tag::forWorkspace($conv->workspace_id)->findOrFail($tagId)
            : Tag::firstOrCreate(
                ['workspace_id' => $conv->workspace_id, 'slug' => Str::slug($data['name'] ?? '')],
                ['name' => $data['name'] ?? '', 'color' => $data['color'] ?? '#075E54']
              );

        $conv->tags()->syncWithoutDetaching([$tag->id => ['added_by' => $request->user()->id]]);
        ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'tag_added', ['tag_id' => $tag->id, 'tag_name' => $tag->name]);
        broadcast(ConversationUpdated::fromModel($conv->fresh(), 'tag_added', ['tag_id' => $tag->id]))->toOthers();

        // Flow auto-enrollment: any active+published flow with
        // trigger_kind='tag_added' and trigger_value=$tag->id picks up
        // this contact. Best-effort — failures here never break the tag
        // operation. Same service powers RoutingEngine's add_tag action.
        app(\App\Services\Flow\FlowEnrollmentService::class)
            ->onConversationTagged($conv, $tag->id);

        return response()->json(['ok' => true, 'tag' => $tag]);
    }

    public function untag(Request $request, int $id, int $tagId): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('tag', $conv);
        $conv->tags()->detach($tagId);
        ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'tag_removed', ['tag_id' => $tagId]);
        broadcast(ConversationUpdated::fromModel($conv->fresh(), 'tag_removed', ['tag_id' => $tagId]))->toOthers();

        return response()->json(['ok' => true]);
    }

    // ----------------------------------------------------------------
    // Per-message actions — mirror the /chat hover-menu surface so the
    // operator gets the same Pin / Star / React / Forward / Delete /
    // Info controls inside team-inbox. Each one resolves the message
    // through the conversation scope so cross-conversation IDs can't be
    // mutated by a curious frontend.
    // ----------------------------------------------------------------

    /**
     * Lookup a Conversation by id WHILE enforcing it belongs to the
     * caller's current workspace. Defence-in-depth alongside the
     * downstream `$this->authorize('view', $conv)` policy check —
     * previously every `Conversation::findOrFail($id)` would return
     * ANY workspace's row if the policy was bypassed or buggy.
     *
     * Reads `current_workspace_id` off the authed user so individual
     * controller actions don't have to pass `$request` through.
     */
    private function findConvInCurrentWorkspace(int $id): Conversation
    {
        // Workspace-shared visibility: any member of the current
        // workspace can open any conversation in it. The downstream
        // `$this->authorize('view', $conv)` is still the canonical
        // access gate for per-role rules (viewer vs agent vs admin)
        // — this helper just hard-stops the lookup from crossing
        // workspace boundaries. Legacy NULL-workspace rows fall back
        // to the original opener via the scope.
        return Conversation::query()->forCurrentWorkspace()->findOrFail($id);
    }

    private function findConvMessage(int $convId, int $msgId): InboxMessage
    {
        $conv = $this->findConvInCurrentWorkspace($convId);
        $this->authorize('view', $conv);
        return InboxMessage::query()
            ->where('conversation_id', $conv->id)
            ->findOrFail($msgId);
    }

    public function messageInfo(Request $request, int $c, int $m): JsonResponse
    {
        $msg = $this->findConvMessage($c, $m);
        return response()->json(['data' => [
            'id'           => $msg->id,
            'direction'    => $msg->direction,
            'status'       => $msg->status,
            'created_at'   => $msg->created_at,
            'sent_at'      => $msg->sent_at,
            'delivered_at' => $msg->delivered_at,
            'read_at'      => $msg->read_at,
            'failure'      => $msg->failure_reason,
        ]]);
    }

    public function messageTogglePin(Request $request, int $c, int $m): JsonResponse
    {
        $msg = $this->findConvMessage($c, $m);
        $wantPin = !$msg->pinned;
        $msg->update(['pinned' => $wantPin]);
        // Fire to Node so the WhatsApp recipient sees the pinned bubble too.
        $waOk = true; $waErr = null;
        try {
            $r = app(\App\Services\InboxDispatcher::class)->pin($msg, $wantPin, 604800);
            $waOk  = (bool) ($r['ok'] ?? false);
            $waErr = $r['error'] ?? null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('pin dispatch threw', ['err' => $e->getMessage()]);
            $waOk  = false;
            $waErr = $e->getMessage();
        }
        return response()->json(['data' => [
            'id'        => $msg->id,
            'pinned'    => (bool) $msg->pinned,
            'wa_ok'     => $waOk,
            'wa_error'  => $waErr ? mb_substr((string) $waErr, 0, 300) : null,
        ]]);
    }

    public function messageToggleStar(Request $request, int $c, int $m): JsonResponse
    {
        $msg = $this->findConvMessage($c, $m);
        $wantStar = !$msg->starred;
        $msg->update(['starred' => $wantStar]);
        $waOk = true; $waErr = null;
        try {
            $r = app(\App\Services\InboxDispatcher::class)->star($msg, $wantStar);
            $waOk  = (bool) ($r['ok'] ?? false);
            $waErr = $r['error'] ?? null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('star dispatch threw', ['err' => $e->getMessage()]);
            $waOk = false; $waErr = $e->getMessage();
        }
        return response()->json(['data' => [
            'id'       => $msg->id,
            'starred'  => (bool) $msg->starred,
            'wa_ok'    => $waOk,
            'wa_error' => $waErr ? mb_substr((string) $waErr, 0, 300) : null,
        ]]);
    }

    /**
     * Delete-for-everyone — soft-delete the InboxMessage row AND
     * revoke the message on WhatsApp so the recipient sees the
     * "This message was deleted" placeholder.
     *
     * Only outbound messages can be revoked (Meta + Baileys enforce
     * this; we mirror that check up-front so a bad request returns
     * 422 cleanly instead of a Node error).
     *
     * Inbound messages can still be soft-deleted server-side (hides
     * them from the thread) — controlled by ?mode=local, which the
     * UI uses for the "Delete for me only" option.
     */
    public function messageDelete(Request $request, int $c, int $m): JsonResponse
    {
        $msg = $this->findConvMessage($c, $m);

        $data = $request->validate([
            'mode' => ['nullable', 'in:everyone,local'],
        ]);
        $mode = $data['mode'] ?? 'everyone';

        $waOk = true; $waErr = null;
        if ($mode === 'everyone') {
            if ($msg->direction !== 'out') {
                return response()->json([
                    'ok'    => false,
                    'error' => 'cannot_revoke_inbound',
                    'message' => 'Only your own messages can be deleted for everyone. Inbound messages can only be hidden locally.',
                ], 422);
            }
            try {
                $r = app(\App\Services\InboxDispatcher::class)->deleteForEveryone($msg);
                $waOk  = (bool) ($r['ok'] ?? false);
                $waErr = $r['error'] ?? null;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('delete dispatch threw', ['err' => $e->getMessage()]);
                $waOk  = false;
                $waErr = $e->getMessage();
            }
        }

        // Soft-delete the row regardless of WhatsApp result. Even if
        // the revoke failed (recipient's app didn't ack within Meta's
        // window), removing it from the operator's inbox is still the
        // right local action; we surface wa_error so the UI can warn.
        $msg->delete();

        return response()->json(['data' => [
            'id'        => $m,
            'deleted'   => true,
            'mode'      => $mode,
            'wa_ok'     => $waOk,
            'wa_error'  => $waErr ? mb_substr((string) $waErr, 0, 300) : null,
        ]]);
    }

    /**
     * Edit-for-everyone. WhatsApp gives senders a 15-minute window in
     * which to edit a message they sent; once that window closes,
     * recipients silently keep the original. We mirror that here so the
     * Node call doesn't fail mid-flight with a cryptic "edit accepted
     * by server but recipient never updates" outcome.
     *
     * Only outbound messages can be edited. Inbound bubbles return 422.
     */
    public function messageEdit(Request $request, int $c, int $m): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:4096'],
        ]);
        $msg = $this->findConvMessage($c, $m);

        if ($msg->direction !== 'out') {
            return response()->json([
                'ok' => false, 'error' => 'cannot_edit_inbound',
                'message' => 'Only your own messages can be edited.',
            ], 422);
        }
        if (!$msg->isEditable()) {
            $anchor = $msg->sent_at ?: $msg->created_at;
            $reason = $anchor && $anchor->lt(now()->subMinutes(\App\Models\InboxMessage::EDIT_WINDOW_MINUTES))
                ? 'edit_window_expired'
                : 'no_wa_message_id';
            return response()->json([
                'ok' => false, 'error' => $reason,
                'message' => $reason === 'edit_window_expired'
                    ? 'WhatsApp only allows edits for ' . \App\Models\InboxMessage::EDIT_WINDOW_MINUTES . ' minutes after sending.'
                    : 'This message was saved before WhatsApp-id tracking was enabled and can\'t be edited.',
            ], 422);
        }

        $newBody = trim((string) $data['body']);
        if ($newBody === '' || $newBody === (string) $msg->body) {
            return response()->json([
                'ok' => false, 'error' => 'no_change',
                'message' => 'New body is empty or identical to the current one.',
            ], 422);
        }

        // Push to WhatsApp first; only persist locally if the wire
        // call succeeds, otherwise the bubble + recipient diverge.
        $waOk = true; $waErr = null;
        try {
            $r = app(\App\Services\InboxDispatcher::class)->editForEveryone($msg, $newBody);
            $waOk  = (bool) ($r['ok'] ?? false);
            $waErr = $r['error'] ?? null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('team-inbox edit dispatch threw', ['err' => $e->getMessage()]);
            $waOk = false; $waErr = $e->getMessage();
        }

        if ($waOk) {
            $msg->update([
                'body'      => $newBody,
                'edited_at' => now(),
            ]);
        }

        return response()->json(['data' => [
            'id'        => $msg->id,
            'body'      => $msg->body,
            'edited_at' => $msg->edited_at,
            'wa_ok'     => $waOk,
            'wa_error'  => $waErr ? mb_substr((string) $waErr, 0, 300) : null,
        ]]);
    }

    public function messageReact(Request $request, int $c, int $m): JsonResponse
    {
        $data  = $request->validate(['emoji' => 'nullable|string|max:8']);
        $msg   = $this->findConvMessage($c, $m);
        $emoji = $data['emoji'] ?? '';
        $msg->update(['reaction' => $emoji ?: null]);
        $waOk = true; $waErr = null;
        try {
            $r = app(\App\Services\InboxDispatcher::class)->reaction($msg, $emoji);
            $waOk  = (bool) ($r['ok'] ?? false);
            $waErr = $r['error'] ?? null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('team-inbox reaction dispatch threw', ['err' => $e->getMessage()]);
            $waOk = false; $waErr = $e->getMessage();
        }
        return response()->json(['data' => [
            'id'       => $msg->id,
            'reaction' => $msg->reaction,
            'wa_ok'    => $waOk,
            'wa_error' => $waErr ? mb_substr((string) $waErr, 0, 300) : null,
        ]]);
    }

    public function messageForward(Request $request, int $c, int $m): JsonResponse
    {
        $data = $request->validate(['target_conversation_id' => 'required|integer']);
        $src  = $this->findConvMessage($c, $m);
        $target = Conversation::forWorkspace($request->user()->current_workspace_id)
            ->findOrFail($data['target_conversation_id']);

        // Recipient phone — pull from target's most recent inbound row,
        // fallback to title parse (same as reply()).
        $lastInbound = InboxMessage::query()
            ->where('conversation_id', $target->id)
            ->where('direction', 'in')
            ->orderByDesc('id')
            ->first(['id', 'from_number']);
        $toNumber = $lastInbound?->from_number;
        if (!$toNumber && $target->title && preg_match('/\+?(\d{8,15})/', (string) $target->title, $m2)) {
            $toNumber = $m2[1];
        }
        // Outbound-first conversation (e.g. created by Quick Send before the
        // customer ever replied) has no inbound row — fall back to the last
        // OUTBOUND message's to_number so it still resolves a recipient.
        if (!$toNumber) {
            $outRow = InboxMessage::query()
                ->where('conversation_id', $target->id)->where('direction', 'out')
                ->whereNotNull('to_number')->orderByDesc('id')->first(['id', 'to_number']);
            if ($outRow && (string) $outRow->to_number !== '') $toNumber = $outRow->to_number;
        }
        $devicePhone = null;
        if ($target->device_id) {
            $device = \App\Models\Device::query()->find($target->device_id);
            if ($device) $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null;
        }

        $rawJid = (string) ($target->raw_jid ?? '');
        $meta   = ['forwarded' => true];
        if ($rawJid !== '') $meta['target_jid'] = $rawJid;

        $copy = InboxMessage::create([
            'conversation_id' => $target->id,
            'user_id'         => $request->user()->id,
            'direction'       => 'out',
            'from_number'     => $devicePhone,
            'to_number'       => $toNumber,
            'body'            => $src->body,
            'media_path'      => $src->media_path,
            'media_type'      => $src->media_type,
            'status'          => 'pending',
            'meta'            => $meta,
        ]);

        // Billing is plan-first via OverflowBilling inside InboxDispatcher::send()
        // — free under the workspace's monthly_messages_limit, 1 wallet credit only
        // on overflow. No wallet pre-gate / charge / refund here (that was wallet-first
        // and double-charged on top of the dispatcher's meter).
        if (!$toNumber && !$rawJid) {
            $copy->update(['status' => 'failed', 'failure_reason' => 'No recipient phone or JID resolved.']);
        } else {
            try {
                $result = app(\App\Services\InboxDispatcher::class)->send($copy, $target->platform ?? 'W');
                if (($result['ok'] ?? false) === true) {
                    // Stash wa_message_id so the forwarded copy can be
                    // pinned / starred / reacted to later.
                    $update = ['status' => 'sent', 'sent_at' => now()];
                    if (!empty($result['provider_id'])) {
                        $existing = is_array($copy->meta) ? $copy->meta : [];
                        $update['meta'] = array_merge($existing, ['wa_message_id' => (string) $result['provider_id']]);
                    }
                    $copy->update($update);
                } else {
                    $copy->update(['status' => 'failed', 'failure_reason' => mb_substr((string) ($result['error'] ?? 'unknown'), 0, 191)]);
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                $copy->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            } catch (\Throwable $e) {
                $copy->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            }
        }

        $target->forceFill([
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
            'preview'          => mb_substr((string) $src->body, 0, 191) ?: '📎 Forwarded',
        ])->save();

        return response()->json(['ok' => true, 'message' => $this->serializeMessage($copy)]);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('reply', $conv);

        $data = $request->validate([
            'body'         => 'nullable|string|max:4096',
            'template_id'  => 'nullable|integer|exists:wa_templates,id',
            // Positional → attribute_key map for {{N}} placeholders the
            // operator inserted via the `/` attribute picker. Optional —
            // only present when the composer recorded one.
            'variable_map' => 'nullable|array',
            'variable_map.*' => 'string|max:64',
        ]);

        // Template path. When `template_id` is set we ignore the typed
        // body and rebuild the message from the WaTemplate — header,
        // footer, and buttons all flow through Message::meta so the
        // dispatcher's mergeButtonsFooter() can hand them to Node.
        $templateMeta = null;
        if (!empty($data['template_id'])) {
            // Workspace-scope so an operator can't send a template body
            // from another tenant by guessing the template id.
            $tpl = \App\Models\WaTemplate::query()->forCurrentWorkspace()->find($data['template_id']);
            if ($tpl) {
                $data['body'] = (string) $tpl->template_body;
                $templateMeta = [
                    'header'  => (string) ($tpl->header ?: ''),
                    'footer'  => (string) ($tpl->footer ?: ''),
                    'buttons' => is_array($tpl->buttons) ? $tpl->buttons : [],
                    // Carry the canonical template identifiers so the
                    // dispatcher can build a type:template Meta payload
                    // (without these it falls back to plain text and
                    // Meta rejects with 131047 outside the 24h window).
                    'template_id'       => (int) $tpl->id,
                    'template_name'     => (string) ($tpl->template_name ?? ''),
                    'template_language' => (string) ($tpl->language ?? 'en'),
                ];
                // Drop empty entries so they don't pollute the payload.
                foreach (['header', 'footer'] as $k) {
                    if ($templateMeta[$k] === '') unset($templateMeta[$k]);
                }
                if (empty($templateMeta['buttons'])) unset($templateMeta['buttons']);
            }
        }

        if (empty($data['body'])) {
            return response()->json(['ok' => false, 'error' => 'empty_body'], 422);
        }

        // Resolve {{N}} → workspace attribute values BEFORE the wallet
        // charge / dispatch — so the customer receives the substituted
        // text, not the placeholder.
        $data['body'] = app(\App\Services\AttributeResolver::class)->resolve(
            $data['body'],
            $data['variable_map'] ?? [],
            (int) $conv->workspace_id,
        );

        // Resolve recipient phone — pull from the most recent inbound
        // message on this conversation, OR from the conversation title
        // as a fallback. We can't query encrypted from_number via SQL,
        // so we read the column on the row directly.
        $lastInbound = InboxMessage::query()
            ->where('conversation_id', $conv->id)
            ->where('direction', 'in')
            ->orderByDesc('id')
            ->first(['id', 'from_number']);
        $toNumber = $lastInbound?->from_number;
        if (!$toNumber && $conv->title) {
            // Title may end with "Name · +91XXXXXXXXX". Pull the digits.
            if (preg_match('/\+?(\d{8,15})/', (string) $conv->title, $m)) {
                $toNumber = $m[1];
            }
        }
        // Outbound-first conversation (e.g. created by Quick Send before the
        // customer ever replied) has no inbound row — fall back to the last
        // OUTBOUND message's to_number so the reply still resolves a recipient.
        if (!$toNumber) {
            $outRow = InboxMessage::query()
                ->where('conversation_id', $conv->id)->where('direction', 'out')
                ->whereNotNull('to_number')->orderByDesc('id')->first(['id', 'to_number']);
            if ($outRow && (string) $outRow->to_number !== '') $toNumber = $outRow->to_number;
        }
        if (!$toNumber) {
            // Conversation originated in Quick Send — its rows live in the
            // `messages` table (not `inbox_messages`), so read the recipient
            // from there when the inbox tables have nothing. This is the case
            // where a Quick Send queue is opened/replied to in the Team Inbox.
            $qsRow = \App\Models\Message::query()
                ->where('conversation_id', $conv->id)->where('direction', 'out')
                ->whereNotNull('to_number')->orderByDesc('id')->first(['id', 'to_number']);
            if ($qsRow && (string) $qsRow->to_number !== '') $toNumber = $qsRow->to_number;
        }

        // Resolve sender device phone for from_number.
        $devicePhone = null;
        if ($conv->device_id) {
            $device = \App\Models\Device::query()->find($conv->device_id);
            if ($device) {
                $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null;
            }
        }

        // Carry the raw JID through to Node. When LID-routed, sending
        // by digits would target a fabricated phone (the LID truncated
        // to 12 digits); we have to use the full JID instead.
        $rawJid = (string) ($conv->raw_jid ?? '');
        $meta = [];
        if ($rawJid !== '') $meta['target_jid'] = $rawJid;
        if (is_array($templateMeta)) $meta = array_merge($meta, $templateMeta);
        $meta = $meta ?: null;

        $msg = InboxMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $request->user()->id,
            'direction'       => 'out',
            'from_number'     => $devicePhone,
            'to_number'       => $toNumber,
            'body'            => $data['body'],
            'status'          => 'pending',
            'meta'            => $meta,
        ]);

        // Actually send via the dispatcher (Baileys / WABA / Twilio
        // depending on the workspace's provider config). Without this
        // the reply just sits in the DB and never reaches the customer.
        // Billing is plan-first via OverflowBilling inside the dispatcher
        // (free under monthly_messages_limit, wallet credit only on overflow)
        // — no wallet pre-gate / charge / refund here.
        \Illuminate\Support\Facades\Log::info('[TEAM-INBOX REPLY] dispatching', [
            'conv_id'    => $conv->id,
            'msg_id'     => $msg->id,
            'to'         => $toNumber,
            'from'       => $devicePhone,
        ]);
        if (!$toNumber && !$rawJid) {
            $msg->update(['status' => 'failed', 'failure_reason' => 'No recipient phone or JID resolved for this conversation.']);
        } else {
            try {
                $result = app(\App\Services\InboxDispatcher::class)->send($msg, $conv->platform ?? 'W');
                if (($result['ok'] ?? false) === true) {
                    // Stash the provider's wa_message_id on the row so
                    // inbound reactions targeting this outbound bubble
                    // can be reverse-matched. Lives on meta to avoid a
                    // schema change.
                    $updateFields = ['status' => 'sent', 'sent_at' => now()];
                    $waId = $result['provider_id'] ?? null;
                    if ($waId) {
                        $existingMeta = is_array($msg->meta) ? $msg->meta : [];
                        $updateFields['meta'] = array_merge($existingMeta, ['wa_message_id' => (string) $waId]);
                    }
                    $msg->update($updateFields);
                } else {
                    $errMsg = (string) ($result['error'] ?? 'unknown');
                    $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($errMsg, 0, 191)]);
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('team-inbox reply dispatch threw', ['err' => $e->getMessage(), 'msg_id' => $msg->id]);
                $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            }
        }

        $conv->forceFill([
            'last_message_at'   => now(),
            'last_outbound_at'  => now(),
            'preview'           => mb_substr($data['body'], 0, 200),
            // An outbound reply means the operator handled the chat — no
            // escalation needed anymore.
            'escalation_due_at' => null,
            'escalation_action' => null,
        ])->save();

        if (!$conv->first_response_at) {
            $this->sla->markFirstResponse($conv);
        }

        AgentStatus::where('user_id', $request->user()->id)->where('workspace_id', $conv->workspace_id)
            ->increment('today_replies');

        broadcast(new MessageReceived($msg->id, $conv->id, $conv->workspace_id, 'out', $request->user()->id))->toOthers();
        AuditLogger::workspace('conversation.replied', $request->user()->id, $conv->workspace_id, 'conversation', $conv->id);
        app(\App\Services\Inbox\OutboundWebhookDispatcher::class)->fire('conversation.replied', $conv->fresh(), [
            'message_id'  => $msg->id,
            'body'        => (string) ($data['body'] ?? ''),
            'by_user_id'  => $request->user()->id,
        ]);

        // DIAGNOSTIC — pairs with [TEAM-INBOX SHOW]. Confirms the reply was
        // stored on the SAME conversation the operator has open + that the AJAX
        // response carries the serialized message the client must append (the
        // realtime broadcast is ->toOthers(), so the sender's own thread relies
        // on this response, not a socket push). If conv_id here matches the open
        // thread and the message is returned but still "not visible", the miss is
        // client-side (append/socket); if conv_id differs, it's a wrong-thread bug.
        $out = $this->serializeMessage($msg);
        \Illuminate\Support\Facades\Log::info('[TEAM-INBOX SEND] reply returned', [
            'conv_id'         => $conv->id,
            'msg_id'          => $msg->id,
            'status'          => $msg->fresh()->status,
            'to_number'       => $toNumber,
            'raw_jid'         => $conv->raw_jid,
            'workspace_id'    => $conv->workspace_id,
            'resp_has_msg'    => !empty($out['id']),
            'resp_body_len'   => strlen((string) ($out['body'] ?? '')),
            'broadcast'       => 'MessageReceived->toOthers (sender NOT pushed — appends from this response)',
        ]);

        return response()->json(['ok' => true, 'message' => $out]);
    }

    /**
     * Media attachment reply. Accepts a single file upload (image / video
     * / document / audio); the composer fires this once per selected file
     * when the operator multi-selects, so the recipient gets each as a
     * separate WhatsApp message. Caption optional.
     */
    public function mediaReply(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('reply', $conv);

        $data = $request->validate([
            'file'    => 'required|file|max:16384',
            'caption' => 'nullable|string|max:1024',
        ]);

        // Resolve recipient phone — same as reply().
        $lastInbound = InboxMessage::query()->where('conversation_id', $conv->id)->where('direction', 'in')
            ->orderByDesc('id')->first(['id', 'from_number']);
        $toNumber = $lastInbound?->from_number;
        if (!$toNumber && $conv->title && preg_match('/\+?(\d{8,15})/', (string) $conv->title, $m)) {
            $toNumber = $m[1];
        }
        // Outbound-first conversation fallback — last OUTBOUND message's to_number.
        if (!$toNumber) {
            $outRow = InboxMessage::query()
                ->where('conversation_id', $conv->id)->where('direction', 'out')
                ->whereNotNull('to_number')->orderByDesc('id')->first(['id', 'to_number']);
            if ($outRow && (string) $outRow->to_number !== '') $toNumber = $outRow->to_number;
        }
        if (!$toNumber) {
            // Conversation originated in Quick Send — its rows live in the
            // `messages` table (not `inbox_messages`), so read the recipient
            // from there when the inbox tables have nothing. This is the case
            // where a Quick Send queue is opened/replied to in the Team Inbox.
            $qsRow = \App\Models\Message::query()
                ->where('conversation_id', $conv->id)->where('direction', 'out')
                ->whereNotNull('to_number')->orderByDesc('id')->first(['id', 'to_number']);
            if ($qsRow && (string) $qsRow->to_number !== '') $toNumber = $qsRow->to_number;
        }
        $devicePhone = null;
        if ($conv->device_id) {
            $device = \App\Models\Device::query()->find($conv->device_id);
            if ($device) $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null;
        }

        // Save file with the same chat-media/<random>__<original> convention
        // the inbound bridge uses, so the renderer can recover the filename.
        $file = $request->file('file');
        $orig = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName() ?: 'file');
        $name = \Illuminate\Support\Str::random(24) . '__' . $orig;
        $mediaPath = $file->storeAs('chat-media', $name, media_disk());

        // Pick the broad media bucket from mime so the dispatcher sends
        // the right WhatsApp type (image/video/audio/document).
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $mediaType = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default                          => 'document',
        };

        $rawJid = (string) ($conv->raw_jid ?? '');
        $meta = $rawJid !== '' ? ['target_jid' => $rawJid] : null;

        $msg = InboxMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $request->user()->id,
            'direction'       => 'out',
            'from_number'     => $devicePhone,
            'to_number'       => $toNumber,
            'body'            => $data['caption'] ?? null,
            'media_path'      => $mediaPath,
            'media_type'      => $mediaType,
            'status'          => 'pending',
            'meta'            => $meta,
        ]);

        // Plan-first billing via OverflowBilling inside InboxDispatcher::send()
        // — no wallet pre-gate / charge / refund here.
        if (!$toNumber && !$rawJid) {
            $msg->update(['status' => 'failed', 'failure_reason' => 'No recipient phone or JID resolved.']);
        } else {
            try {
                $result = app(\App\Services\InboxDispatcher::class)->send($msg, $conv->platform ?? 'W');
                if (($result['ok'] ?? false) === true) {
                    $update = ['status' => 'sent', 'sent_at' => now()];
                    if (!empty($result['provider_id'])) {
                        $existing = is_array($msg->meta) ? $msg->meta : [];
                        $update['meta'] = array_merge($existing, ['wa_message_id' => (string) $result['provider_id']]);
                    }
                    $msg->update($update);
                } else {
                    $errMsg = (string) ($result['error'] ?? 'unknown');
                    $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($errMsg, 0, 191)]);
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            } catch (\Throwable $e) {
                $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            }
        }

        $previewEmoji = match ($mediaType) {
            'image' => '📷', 'video' => '🎥', 'audio' => '🎵', default => '📎',
        };
        $conv->forceFill([
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
            'preview'          => $previewEmoji . ' ' . ucfirst($mediaType),
        ])->save();

        if (!$conv->first_response_at) $this->sla->markFirstResponse($conv);

        AgentStatus::where('user_id', $request->user()->id)->where('workspace_id', $conv->workspace_id)
            ->increment('today_replies');

        broadcast(new MessageReceived($msg->id, $conv->id, $conv->workspace_id, 'out', $request->user()->id))->toOthers();
        app(\App\Services\Inbox\OutboundWebhookDispatcher::class)->fire('conversation.replied', $conv->fresh(), [
            'message_id' => $msg->id,
            'media_type' => $mediaType,
            'caption'    => $data['caption'] ?? null,
            'by_user_id' => $request->user()->id,
        ]);

        return response()->json(['ok' => true, 'message' => $this->serializeMessage($msg)]);
    }

    /**
     * Voice note reply. The browser records via MediaRecorder, posts the
     * audio Blob here, and we drop it on chat-media/ and dispatch as an
     * audio message with `ptt=true` so Baileys / WABA render it as a
     * WhatsApp voice note (the round play-button bubble) rather than a
     * generic audio file.
     */
    /**
     * Send a WhatsApp catalog message into the conversation.
     *
     * Modes:
     *   spm  — single product (Meta SPM)
     *   mpm  — multi-product list (matches WATI screenshot)
     *   link — generic catalog message with a thumbnail product
     *
     * SPM/MPM require the conversation's device to be wired to a
     * Meta/360dialog provider AND the workspace to have a catalog
     * linked. We surface a clean 422 error otherwise so the UI can
     * tell the operator why.
     *
     * Note: the 24-hour conversation-window rule is a Meta-side
     * constraint, not ours. If we send outside it, Meta responds
     * with error 131047 ("Re-engagement message") and we forward
     * that error message to the operator.
     */
    public function catalogContent(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('reply', $conv);

        $data = $request->validate([
            'mode'                  => ['required', 'in:spm,mpm,link'],
            'body'                  => ['nullable', 'string', 'max:1024'],
            'header'                => ['nullable', 'string', 'max:60'],
            'footer'                => ['nullable', 'string', 'max:60'],
            'product_retailer_ids'  => ['nullable', 'array', 'max:30'],
            'product_retailer_ids.*'=> ['string', 'max:100'],
            'product_id'            => ['nullable', 'integer'], // for SPM
        ]);

        $wsId = $conv->workspace_id;
        $catalog = \App\Models\WaCatalog::where('workspace_id', $wsId)->first();
        // ── If no WABA catalog is bound, fall back to Baileys carousel
        //    via the Node bridge. Same UX from the operator's side.
        if (!$catalog) {
            return $this->sendViaBaileys($request, $conv, $data);
        }

        // Resolve recipient phone — conversations identify customers
        // by raw_jid (the WhatsApp JID, usually phone digits). Falls
        // back to alt_jid if the primary jid isn't set.
        $toWaId = $conv->raw_jid ?? $conv->alt_jid ?? null;
        if (!$toWaId) {
            return response()->json(['ok' => false, 'error' => 'no_recipient'], 422);
        }
        $toWaId = preg_replace('/\D+/', '', (string) $toWaId);

        // Plan-first billing — this catalog send does NOT pass through
        // InboxDispatcher::send(), so OverflowBilling isn't applied by the
        // dispatcher. Meter it here: free under the workspace's
        // monthly_messages_limit, 1 wallet credit only on overflow. $used
        // mirrors InboxDispatcher::guardMonthlyMessagesLimit() — outbound
        // rows across inbox_messages + messages this calendar month.
        try {
            $wsObj = \App\Models\Workspace::find((int) $conv->workspace_id);
            if ($wsObj) {
                $monthStart = now()->startOfMonth();
                $used = \App\Models\InboxMessage::query()
                    ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $conv->workspace_id))
                    ->where('direction', 'out')
                    ->where('created_at', '>=', $monthStart)
                    ->count();
                $userIds = \DB::table('workspace_user')->where('workspace_id', $conv->workspace_id)->pluck('user_id');
                if ($userIds->isNotEmpty()) {
                    $used += \DB::table('messages')
                        ->whereIn('user_id', $userIds)
                        ->where('direction', 'out')
                        ->where('created_at', '>=', $monthStart)
                        ->count();
                }
                \App\Services\OverflowBilling::consumeOne($wsObj, $used);
            }
        } catch (\App\Exceptions\PlanLimitReachedException $e) {
            return response()->json([
                'ok' => false, 'error' => 'plan_limit',
                'message' => $e->getMessage(),
            ], 402);
        }

        try {
            $provider = \App\Services\WhatsAppCatalog\WhatsAppCatalogFactory::forCatalog($catalog);

            $result = match ($data['mode']) {
                'spm' => $this->sendSPMFromRequest($provider, $toWaId, $data, (int) $conv->workspace_id),
                'mpm' => $this->sendMPMFromRequest($provider, $toWaId, $data, (int) $conv->workspace_id),
                'link'=> $provider->sendCatalogLink(
                    $toWaId,
                    $data['body'] ?: ('Check out our catalog: https://wa.me/c/' . preg_replace('/\D+/', '', $catalog->phone_number_id ?: $toWaId))
                ),
            };

            // Persist as an outbound inbox message so the thread shows
            // a record of what was sent. Body is a summary string.
            $summary = match ($data['mode']) {
                'spm'  => '[Catalog · single product] ' . ($data['body'] ?? ''),
                'mpm'  => '[Catalog · ' . count($data['product_retailer_ids'] ?? []) . ' products] ' . ($data['body'] ?? ''),
                'link' => '[Catalog link]',
            };
            // inbox_messages has no workspace_id / kind / wa_message_id
            // columns — we stash those inside meta JSON instead. The
            // catalog activity query filters by meta->kind='catalog'.
            //
            // We also denormalise product summaries here so the team-
            // inbox can render the catalog tile later without an extra
            // wa_products join per message render.
            $productSummaries = [];
            $resolveIds = [];
            if (!empty($data['product_id'])) $resolveIds[] = $data['product_id'];
            if (!empty($data['product_retailer_ids'])) {
                $resolveRids = $data['product_retailer_ids'];
            } else { $resolveRids = []; }
            if (!empty($resolveIds) || !empty($resolveRids)) {
                $rows = \App\Models\WaProduct::where('workspace_id', $wsId)
                    ->where(function ($q) use ($resolveIds, $resolveRids) {
                        if (!empty($resolveIds)) $q->whereIn('id', $resolveIds);
                        if (!empty($resolveRids)) {
                            $q->orWhereIn('meta_retailer_id', $resolveRids)->orWhereIn('sku', $resolveRids);
                        }
                    })->get(['id', 'name', 'image_url', 'price_minor', 'currency_code', 'meta_retailer_id', 'sku']);
                foreach ($rows as $p) {
                    $productSummaries[] = [
                        'id'           => $p->id,
                        'name'         => $p->name,
                        'image_url'    => $p->image_url,
                        'price_minor'  => $p->price_minor,
                        'currency'     => $p->currency_code,
                        'price_display'=> $p->price_display,
                        'retailer_id'  => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id),
                    ];
                }
            }

            \App\Models\InboxMessage::create([
                'conversation_id' => $conv->id,
                'direction'       => 'out',
                'user_id'         => $request->user()->id,
                'body'            => $data['body'] ?? '',
                'from_number'     => $conv->device?->phone_number,
                'to_number'       => $toWaId,
                'status'          => 'sent',
                'meta'            => [
                    'kind'             => 'catalog',
                    'mode'             => $data['mode'],
                    'provider'         => 'waba',
                    'wa_message_id'    => $result['messages'][0]['id'] ?? null,
                    'catalog_payload'  => $result,
                    'products'         => $productSummaries,
                ],
                'sent_at'         => now(),
            ]);

            return response()->json([
                'ok'      => true,
                'mode'    => $data['mode'],
                'message_id' => $result['messages'][0]['id'] ?? null,
            ]);
        } catch (\App\Exceptions\WhatsAppCatalogException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'provider_error',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 502);
        } catch (\Throwable $e) {
            \Log::error('catalogContent failed', ['conv' => $id, 'err' => $e->getMessage()]);
            return response()->json([
                'ok' => false, 'error' => 'server_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fallback path when the workspace has no Meta catalog bound.
     * Sends a Baileys-native carousel (or single image+caption for
     * SPM, or plain text storefront link for link-mode) via the
     * Node bridge.
     */
    private function sendViaBaileys(Request $request, $conv, array $data): JsonResponse
    {
        // Identify the sending device. Conversation knows its device.
        $device = $conv->device ?? \App\Models\Device::find($conv->device_id ?? 0);
        if (!$device) {
            return response()->json([
                'ok' => false, 'error' => 'no_device',
                'message' => 'Conversation has no sending device assigned.',
            ], 422);
        }
        $toWaId = preg_replace('/\D+/', '', (string) ($conv->raw_jid ?? $conv->alt_jid ?? ''));
        if (!$toWaId) return response()->json(['ok' => false, 'error' => 'no_recipient'], 422);

        // Plan-first billing — Baileys catalog send bypasses InboxDispatcher,
        // so meter here exactly like the WABA catalog path above (free under
        // monthly_messages_limit, wallet credit only on overflow).
        try {
            $wsObj = \App\Models\Workspace::find((int) $conv->workspace_id);
            if ($wsObj) {
                $monthStart = now()->startOfMonth();
                $used = \App\Models\InboxMessage::query()
                    ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $conv->workspace_id))
                    ->where('direction', 'out')
                    ->where('created_at', '>=', $monthStart)
                    ->count();
                $userIds = \DB::table('workspace_user')->where('workspace_id', $conv->workspace_id)->pluck('user_id');
                if ($userIds->isNotEmpty()) {
                    $used += \DB::table('messages')
                        ->whereIn('user_id', $userIds)
                        ->where('direction', 'out')
                        ->where('created_at', '>=', $monthStart)
                        ->count();
                }
                \App\Services\OverflowBilling::consumeOne($wsObj, $used);
            }
        } catch (\App\Exceptions\PlanLimitReachedException $e) {
            return response()->json([
                'ok' => false, 'error' => 'plan_limit',
                'message' => $e->getMessage(),
            ], 402);
        }

        try {
            $svc = \App\Services\WhatsAppCatalog\BaileysCatalogService::make();
            $opts = [
                'header' => $data['header'] ?? 'Our catalog',
                'body'   => $data['body']   ?? 'Tap a product to learn more',
                'footer' => $data['footer'] ?? '',
            ];

            if ($data['mode'] === 'link') {
                $shop = \App\Models\WaStorefront::where('workspace_id', $conv->workspace_id)->orderByDesc('id')->first();
                if (!$shop) {
                    return response()->json(['ok' => false, 'error' => 'no_shop',
                        'message' => 'No storefront set up — create one at /connect?platform=wa-store first.'], 422);
                }
                $result = $svc->sendStorefrontLink($device, $toWaId, $shop->public_url, $data['body'] ?? '');
            } else {
                // SPM or MPM — fetch the actual products by retailer_id
                $rids = $data['product_retailer_ids'] ?? [];
                if (!empty($data['product_id']) && empty($rids)) {
                    $rids = [(string) $data['product_id']];
                }
                $products = \App\Models\WaProduct::where('workspace_id', $conv->workspace_id)
                    ->where(function ($q) use ($rids, $data) {
                        if (!empty($data['product_id'])) {
                            $q->where('id', $data['product_id']);
                        }
                        if (!empty($rids)) {
                            $q->orWhereIn('meta_retailer_id', $rids)->orWhereIn('sku', $rids);
                        }
                    })->get();
                if ($products->isEmpty()) {
                    return response()->json(['ok' => false, 'error' => 'no_products'], 422);
                }
                $result = $svc->sendCarousel($device, $toWaId, $products, $opts);
            }

            // Denormalise product details so the team-inbox renders a
            // proper catalog tile without re-querying wa_products on
            // every thread paint.
            $productSummaries = [];
            if (isset($products) && $products) {
                foreach ($products as $p) {
                    $productSummaries[] = [
                        'id'           => $p->id,
                        'name'         => $p->name,
                        'image_url'    => $p->image_url,
                        'price_minor'  => $p->price_minor,
                        'currency'     => $p->currency_code,
                        'price_display'=> $p->price_display,
                        'retailer_id'  => $p->meta_retailer_id ?: ($p->sku ?: 'wsn-' . $p->id),
                    ];
                }
            }

            \App\Models\InboxMessage::create([
                'conversation_id' => $conv->id,
                'direction'       => 'out',
                'user_id'         => $request->user()->id,
                'body'            => $data['body'] ?? '',
                'from_number'     => $device->phone_number,
                'to_number'       => $toWaId,
                'status'          => 'sent',
                'meta'            => [
                    'kind'          => 'catalog',
                    'mode'          => $data['mode'],
                    'provider'      => 'baileys',
                    'wa_message_id' => $result['messageId'] ?? null,
                    'response'      => $result,
                    'products'      => $productSummaries,
                ],
                'sent_at'         => now(),
            ]);

            return response()->json([
                'ok' => true,
                'mode' => $data['mode'],
                'provider' => 'baileys',
                'message_id' => $result['messageId'] ?? null,
            ]);
        } catch (\App\Exceptions\WhatsAppCatalogException $e) {
            return response()->json([
                'ok' => false, 'error' => 'baileys_error',
                'message' => $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            \Log::error('catalogContent (baileys) failed', ['conv' => $conv->id, 'err' => $e->getMessage()]);
            return response()->json([
                'ok' => false, 'error' => 'server_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function sendSPMFromRequest($provider, string $toWaId, array $data, int $workspaceId): array
    {
        $rid = null;
        if (!empty($data['product_id'])) {
            // Workspace-scope so an operator can't reach a product
            // from another tenant by guessing its id.
            $p = \App\Models\WaProduct::query()
                ->where('id', $data['product_id'])
                ->where('workspace_id', $workspaceId)
                ->first();
            $rid = $p?->meta_retailer_id ?: ($p?->sku ?: ($p ? 'wsn-' . $p->id : null));
        }
        if (!$rid && !empty($data['product_retailer_ids'])) {
            $rid = $data['product_retailer_ids'][0];
        }
        if (!$rid) {
            throw new \App\Exceptions\WhatsAppCatalogException('SPM requires a product_id or product_retailer_id.');
        }
        return $provider->sendSPM($toWaId, $rid, $data['body'] ?? null, $data['footer'] ?? null);
    }

    private function sendMPMFromRequest($provider, string $toWaId, array $data, int $workspaceId): array
    {
        $rids = $data['product_retailer_ids'] ?? [];
        if (empty($rids)) {
            throw new \App\Exceptions\WhatsAppCatalogException('MPM requires at least one product.');
        }
        // Group by category for nicer sections. Workspace-scope first
        // so a forged retailer id can't pull category labels from
        // another tenant's catalog.
        $products = \App\Models\WaProduct::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($q) use ($rids) {
                $q->whereIn('meta_retailer_id', $rids)
                  ->orWhereIn('sku', $rids);
            })
            ->get();
        $byRid = [];
        foreach ($products as $p) {
            $byRid[$p->meta_retailer_id ?: $p->sku] = $p;
        }
        $sections = [];
        foreach ($rids as $rid) {
            $cat = $byRid[$rid]?->category ?: 'Featured';
            $sections[$cat][] = $rid;
        }
        $cleanSections = [];
        foreach ($sections as $title => $ids) {
            $cleanSections[] = ['title' => $title, 'product_retailer_ids' => $ids];
        }

        return $provider->sendMPM(
            $toWaId,
            $data['header'] ?? 'Our catalog',
            $data['body']   ?? 'Tap a product to learn more',
            $cleanSections,
            $data['footer'] ?? null,
        );
    }

    public function voiceNote(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('reply', $conv);

        $data = $request->validate([
            'audio'    => 'required|file|max:16384', // 16MB cap, matches inbound bridge
            'duration' => 'nullable|integer|min:0|max:600', // seconds, advisory
        ]);

        // Resolve recipient phone — same logic as reply().
        $lastInbound = InboxMessage::query()
            ->where('conversation_id', $conv->id)
            ->where('direction', 'in')
            ->orderByDesc('id')
            ->first(['id', 'from_number']);
        $toNumber = $lastInbound?->from_number;
        if (!$toNumber && $conv->title && preg_match('/\+?(\d{8,15})/', (string) $conv->title, $m)) {
            $toNumber = $m[1];
        }
        // Outbound-first conversation fallback — last OUTBOUND message's to_number.
        if (!$toNumber) {
            $outRow = InboxMessage::query()
                ->where('conversation_id', $conv->id)->where('direction', 'out')
                ->whereNotNull('to_number')->orderByDesc('id')->first(['id', 'to_number']);
            if ($outRow && (string) $outRow->to_number !== '') $toNumber = $outRow->to_number;
        }
        if (!$toNumber) {
            // Conversation originated in Quick Send — its rows live in the
            // `messages` table (not `inbox_messages`), so read the recipient
            // from there when the inbox tables have nothing. This is the case
            // where a Quick Send queue is opened/replied to in the Team Inbox.
            $qsRow = \App\Models\Message::query()
                ->where('conversation_id', $conv->id)->where('direction', 'out')
                ->whereNotNull('to_number')->orderByDesc('id')->first(['id', 'to_number']);
            if ($qsRow && (string) $qsRow->to_number !== '') $toNumber = $qsRow->to_number;
        }
        $devicePhone = null;
        if ($conv->device_id) {
            $device = \App\Models\Device::query()->find($conv->device_id);
            if ($device) {
                $devicePhone = preg_replace('/\D+/', '', (string) ($device->country_code . $device->phone_number)) ?: null;
            }
        }

        // Save the audio file. MediaRecorder typically produces webm/opus
        // (Chrome/Edge) or ogg/opus (Firefox) or mp4 (Safari). We save
        // as-is — Baileys handles webm/opus + ogg/opus natively and the
        // dispatcher tells it `audio/ogg; codecs=opus` mimetype via the
        // ptt branch, so the recipient gets a voice-note bubble.
        $file = $request->file('audio');
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'webm');
        if (!in_array($ext, ['webm', 'ogg', 'mp3', 'm4a', 'aac', 'wav', 'mp4', 'oga'], true)) {
            $ext = 'webm';
        }
        $base = \Illuminate\Support\Str::random(24);
        $mediaPath = $file->storeAs('chat-media', $base . '.' . $ext, media_disk());
        if (empty($mediaPath)) {
            // storeAs returned false → the media disk write FAILED. Do NOT
            // create a message with no media, or the dispatcher sends it as
            // empty text and Meta rejects with "text.body is required". This is
            // a storage problem (misconfigured Cloud Storage, or a non-writable
            // local disk) — the same reason inbound media shows "unavailable".
            \Illuminate\Support\Facades\Log::error('[TEAM-INBOX VOICE] storage write FAILED', [
                'conv_id' => $conv->id,
                'disk'    => media_disk(),
            ]);
            return response()->json([
                'ok'    => false,
                'error' => 'Voice upload failed — media storage is not writable. If Cloud Storage is enabled (Admin → Storage), verify its credentials or switch it off to use local; otherwise make storage/app/public writable and run "php artisan storage:link".',
            ], 422);
        }

        $meta = ['ptt' => true];
        $rawJid = (string) ($conv->raw_jid ?? '');
        if ($rawJid !== '') $meta['target_jid'] = $rawJid;
        if (!empty($data['duration'])) $meta['duration'] = (int) $data['duration'];

        $msg = InboxMessage::create([
            'conversation_id' => $conv->id,
            'user_id'         => $request->user()->id,
            'direction'       => 'out',
            'from_number'     => $devicePhone,
            'to_number'       => $toNumber,
            'body'            => null,
            'media_path'      => $mediaPath,
            'media_type'      => 'audio',
            'status'          => 'pending',
            'meta'            => $meta,
        ]);

        // Plan-first billing via OverflowBilling inside InboxDispatcher::send()
        // — no wallet pre-gate / charge / refund here.
        \Illuminate\Support\Facades\Log::info('[TEAM-INBOX VOICE] dispatching', [
            'conv_id'    => $conv->id,
            'msg_id'     => $msg->id,
            'to'         => $toNumber,
            'from'       => $devicePhone,
            'media_path' => $mediaPath,
        ]);

        if (!$toNumber && !$rawJid) {
            $msg->update(['status' => 'failed', 'failure_reason' => 'No recipient phone or JID resolved for this conversation.']);
        } else {
            try {
                $result = app(\App\Services\InboxDispatcher::class)->send($msg, $conv->platform ?? 'W');
                if (($result['ok'] ?? false) === true) {
                    // Stash the provider's wa_message_id so future pin/star/
                    // react actions on this voice note can target it.
                    $update = ['status' => 'sent', 'sent_at' => now()];
                    if (!empty($result['provider_id'])) {
                        $existing = is_array($msg->meta) ? $msg->meta : [];
                        $update['meta'] = array_merge($existing, ['wa_message_id' => (string) $result['provider_id']]);
                    }
                    $msg->update($update);
                } else {
                    $errMsg = (string) ($result['error'] ?? 'unknown');
                    $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($errMsg, 0, 191)]);
                }
            } catch (\App\Exceptions\PlanLimitReachedException $e) {
                $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('team-inbox voice dispatch threw', ['err' => $e->getMessage(), 'msg_id' => $msg->id]);
                $msg->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 191)]);
            }
        }

        $conv->forceFill([
            'last_message_at'  => now(),
            'last_outbound_at' => now(),
            'preview'          => '🎤 Voice note',
        ])->save();

        if (!$conv->first_response_at) {
            $this->sla->markFirstResponse($conv);
        }

        AgentStatus::where('user_id', $request->user()->id)->where('workspace_id', $conv->workspace_id)
            ->increment('today_replies');

        broadcast(new MessageReceived($msg->id, $conv->id, $conv->workspace_id, 'out', $request->user()->id))->toOthers();
        AuditLogger::workspace('conversation.voice_replied', $request->user()->id, $conv->workspace_id, 'conversation', $conv->id);

        return response()->json(['ok' => true, 'message' => $this->serializeMessage($msg)]);
    }

    // ----------------------------------------------------------------
    // Internal notes
    // ----------------------------------------------------------------

    public function addNote(Request $request, int $id): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('note', $conv);

        $data = $request->validate([
            'body'     => 'required|string|max:8000',
            'mentions' => 'nullable|array',
            'mentions.*.user_id' => 'integer',
            'mentions.*.name'    => 'string|max:120',
        ]);

        $note = ConversationNote::create([
            'conversation_id' => $conv->id,
            'workspace_id'    => $conv->workspace_id,
            'user_id'         => $request->user()->id,
            'body'            => $data['body'],
            'mentions'        => $data['mentions'] ?? [],
        ]);

        ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'note_added', ['note_id' => $note->id]);

        foreach ($data['mentions'] ?? [] as $m) {
            $uid = (int) ($m['user_id'] ?? 0);
            if ($uid && $uid !== $request->user()->id) {
                $excerpt = mb_substr(strip_tags($data['body']), 0, 160);
                $this->notify->notifyMention($conv, $uid, $request->user()->id, $excerpt);
                broadcast(new MentionReceived($uid, $conv->id, $conv->workspace_id, $request->user()->id, $excerpt))->toOthers();
            }
        }

        broadcast(NoteAdded::fromModel($note))->toOthers();
        AuditLogger::workspace('note.added', $request->user()->id, $conv->workspace_id, 'note', $note->id);

        return response()->json(['ok' => true, 'note' => $this->serializeNote($note)]);
    }

    public function deleteNote(Request $request, int $id, int $noteId): JsonResponse
    {
        $conv = $this->findConvInCurrentWorkspace($id);
        $this->authorize('note', $conv);
        $note = ConversationNote::where('conversation_id', $conv->id)->findOrFail($noteId);
        if ($note->user_id !== $request->user()->id && !WorkspacePermissions::userCan($request->user(), 'inbox.view_all_teams', $conv->workspace_id)) {
            abort(403);
        }
        $note->delete();
        AuditLogger::workspace('note.deleted', $request->user()->id, $conv->workspace_id, 'note', $note->id);

        return response()->json(['ok' => true]);
    }

    // ----------------------------------------------------------------
    // Bulk
    // ----------------------------------------------------------------

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'action' => 'required|in:assign,resolve,reopen,snooze,priority,tag,spam,archive,unarchive,pin,unpin,mute,unmute,delete',
            'user_id'  => 'nullable|integer',
            'team_id'  => 'nullable|integer',
            'tag_id'   => 'nullable|integer',
            'priority' => 'nullable|in:' . implode(',', Conversation::PRIORITIES),
            'until'    => 'nullable|date',
        ]);

        $convs = Conversation::forWorkspace($request->user()->current_workspace_id)
            ->whereIn('id', $data['ids'])->get();

        $touched = 0;
        $resolvedCount = 0;
        foreach ($convs as $conv) {
            try {
                $this->authorize('bulk', $conv);
            } catch (\Throwable $e) {
                continue;
            }

            switch ($data['action']) {
                case 'assign':
                    $this->assignment->assign($conv, $data['user_id'] ?? null, $data['team_id'] ?? null, 'manual', $request->user()->id);
                    break;
                case 'resolve':
                    $bAgentId = $conv->assignee_agent_id;
                    $conv->forceFill(['inbox_status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolved_by_agent_id' => $bAgentId])->save();
                    ConversationEvent::record($conv->id, $conv->workspace_id, $request->user()->id, 'resolved', [
                        'bulk' => true, 'by' => $request->user()->name,
                        'agent_id' => $bAgentId, 'agent_name' => $bAgentId ? optional(AiAgent::find($bAgentId))->name : null,
                    ]);
                    $resolvedCount++;
                    break;
                case 'reopen':
                    $conv->forceFill(['inbox_status' => 'open', 'resolved_at' => null])->save();
                    break;
                case 'snooze':
                    $conv->forceFill(['inbox_status' => 'snoozed', 'snoozed_until' => $data['until'] ?? now()->addHour()])->save();
                    break;
                case 'priority':
                    if (!empty($data['priority'])) {
                        $conv->forceFill(['priority' => $data['priority']])->save();
                    }
                    break;
                case 'tag':
                    if (!empty($data['tag_id'])) {
                        $conv->tags()->syncWithoutDetaching([$data['tag_id']]);
                    }
                    break;
                case 'spam':
                    $conv->forceFill(['is_spam' => true, 'inbox_status' => 'spam'])->save();
                    break;
                // WhatsApp-style list controls (single row via [id] or multi-select).
                case 'archive':
                    $conv->forceFill(['archived' => true])->save();
                    break;
                case 'unarchive':
                    $conv->forceFill(['archived' => false])->save();
                    break;
                case 'pin':
                    $conv->forceFill(['pinned_at' => now()])->save();
                    break;
                case 'unpin':
                    $conv->forceFill(['pinned_at' => null])->save();
                    break;
                case 'mute':
                    $conv->forceFill(['muted_at' => now()])->save();
                    break;
                case 'unmute':
                    $conv->forceFill(['muted_at' => null])->save();
                    break;
                case 'delete':
                    // Permanent delete (no SoftDeletes on Conversation). Clear the
                    // conversation's own child rows first so nothing is orphaned;
                    // FK-cascaded tables (participants/events) drop with the row.
                    try {
                        $conv->inboxMessages()->delete();
                        $conv->messages()->delete();
                        $conv->tags()->detach();
                    } catch (\Throwable $e) { /* best-effort child cleanup */ }
                    $conv->delete();
                    break;
            }
            $touched++;
        }

        if ($resolvedCount > 0) {
            AgentStatus::where('user_id', $request->user()->id)
                ->where('workspace_id', $request->user()->current_workspace_id)
                ->increment('today_resolutions', $resolvedCount);
        }

        AuditLogger::workspace('bulk.' . $data['action'], $request->user()->id, $request->user()->current_workspace_id, null, null, [
            'count' => $touched, 'ids' => $data['ids'],
        ]);
        return response()->json(['ok' => true, 'touched' => $touched]);
    }

    // ----------------------------------------------------------------
    // Workspace settings sub-resources
    // ----------------------------------------------------------------

    public function teamsIndex(Request $request): JsonResponse
    {
        $wsId = $request->user()->current_workspace_id;
        return response()->json(Team::forWorkspace($wsId)->with('members:id,name')->orderBy('sort')->get());
    }

    public function teamsStore(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'team.manage')) abort(403);
        $data = $request->validate([
            'name' => 'required|string|max:64',
            'color' => 'nullable|string|max:16|regex:/^#?[0-9A-Za-z_-]{1,16}$/',
            'description' => 'nullable|string|max:255',
            'assignment_strategy' => 'nullable|in:' . implode(',', Team::STRATEGIES),
            'members' => 'nullable|array',
            'members.*' => 'integer',
            // Multi-device whitelist. null/empty = any device.
            'device_ids' => 'nullable|array',
            'device_ids.*' => 'integer|exists:devices,id',
        ]);
        $data['device_ids'] = $this->intersectWorkspaceDevices($request, $data['device_ids'] ?? []);
        $team = Team::create([
            'workspace_id' => $request->user()->current_workspace_id,
            'name'         => $data['name'],
            'slug'         => Str::slug($data['name']) . '-' . Str::random(4),
            'color'        => $data['color'] ?? '#075E54',
            'description'  => $data['description'] ?? null,
            'assignment_strategy' => $data['assignment_strategy'] ?? 'manual',
            'device_ids'   => $data['device_ids'] ?: null,
        ]);
        if (!empty($data['members'])) {
            $team->members()->attach($data['members']);
        }
        AuditLogger::workspace('team.created', $request->user()->id, $team->workspace_id, 'team', $team->id);
        return response()->json(['ok' => true, 'team' => $team->load('members:id,name')]);
    }

    public function teamsUpdate(Request $request, int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        $this->authorize('update', $team);
        $data = $request->validate([
            'name' => 'sometimes|string|max:64',
            'color' => 'sometimes|string|max:16|regex:/^#?[0-9A-Za-z_-]{1,16}$/',
            'description' => 'nullable|string|max:255',
            'assignment_strategy' => 'sometimes|in:' . implode(',', Team::STRATEGIES),
            'members' => 'nullable|array',
            'members.*' => 'integer',
            'device_ids' => 'nullable|array',
            'device_ids.*' => 'integer|exists:devices,id',
        ]);
        if (array_key_exists('device_ids', $data)) {
            $data['device_ids'] = $this->intersectWorkspaceDevices($request, $data['device_ids'] ?? []) ?: null;
        }
        $team->update(collect($data)->except('members')->all());
        if (array_key_exists('members', $data)) {
            $team->members()->sync($data['members']);
        }
        AuditLogger::workspace('team.updated', $request->user()->id, $team->workspace_id, 'team', $team->id);
        return response()->json(['ok' => true, 'team' => $team->fresh()->load('members:id,name')]);
    }

    /**
     * Intersect the caller's claimed device id list with devices the
     * current workspace actually owns. Stops a forged payload from
     * pinning a team / SLA policy to another workspace's device.
     * Returns an array of ints; empty array means "no constraint".
     */
    private function intersectWorkspaceDevices(Request $request, array $claimed): array
    {
        if (empty($claimed)) return [];
        $wsUserIds = \App\Models\User::query()
            ->where('current_workspace_id', $request->user()->current_workspace_id)
            ->pluck('id');
        return \App\Models\Device::query()
            ->whereIn('user_id', $wsUserIds)
            ->whereIn('id', $claimed)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    public function teamsDestroy(Request $request, int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        $this->authorize('delete', $team);
        $team->delete();
        AuditLogger::workspace('team.deleted', $request->user()->id, $team->workspace_id, 'team', $team->id);
        return response()->json(['ok' => true]);
    }

    public function tagsIndex(Request $request): JsonResponse
    {
        $wsId = $request->user()->current_workspace_id;
        return response()->json(Tag::forWorkspace($wsId)->orderBy('name')->get());
    }

    public function tagsStore(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'tag.manage')) abort(403);

        $wsId = $request->user()->current_workspace_id;
        \App\Services\PlanLimitGuard::check(
            $request->user()->currentWorkspace,
            'tags_limit',
            Tag::where('workspace_id', $wsId)->count(),
        );

        $data = $request->validate([
            'name'  => 'required|string|max:64',
            'color' => 'nullable|string|max:16|regex:/^#?[0-9A-Za-z_-]{1,16}$/',
        ]);
        $tag = Tag::firstOrCreate(
            ['workspace_id' => $request->user()->current_workspace_id, 'slug' => Str::slug($data['name'])],
            ['name' => $data['name'], 'color' => $data['color'] ?? '#075E54'],
        );
        return response()->json(['ok' => true, 'tag' => $tag]);
    }

    public function tagsDestroy(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'tag.manage')) abort(403);
        $tag = Tag::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        $tag->delete();
        return response()->json(['ok' => true]);
    }

    public function savedRepliesIndex(Request $request): JsonResponse
    {
        $wsId = $request->user()->current_workspace_id;
        return response()->json(SavedReply::forWorkspace($wsId)->accessibleBy($request->user()->id)->orderByDesc('used_count')->get());
    }

    public function savedRepliesStore(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'savedreply.manage')) abort(403);
        // Plan: numeric cap on saved replies (workspace scope).
        $ws = $request->user()->currentWorkspace;
        \App\Services\PlanLimitGuard::check(
            $ws, 'saved_replies_limit',
            SavedReply::where('workspace_id', $ws->id)->count(),
        );

        $data = $request->validate([
            'shortcut' => 'required|string|max:64',
            'title'    => 'required|string|max:128',
            'body'     => 'required|string|max:4000',
            'category' => 'nullable|string|max:64',
            'personal' => 'nullable|boolean',
        ]);
        $row = SavedReply::create([
            'workspace_id' => $request->user()->current_workspace_id,
            'user_id'      => !empty($data['personal']) ? $request->user()->id : null,
            'shortcut'     => Str::slug($data['shortcut'], '_'),
            'title'        => $data['title'],
            'body'         => $data['body'],
            'category'     => $data['category'] ?? null,
        ]);
        return response()->json(['ok' => true, 'saved_reply' => $row]);
    }

    /**
     * Mark a saved reply as "used" so its used_count climbs. Called by
     * the frontend right after the operator inserts a reply via the
     * picker or `/shortcut` trigger. We do this in its own endpoint so
     * the bootstrap sort by used_count reflects actual operator behavior.
     */
    public function savedRepliesUse(Request $request, int $id): JsonResponse
    {
        $row = SavedReply::forWorkspace($request->user()->current_workspace_id)
            ->accessibleBy($request->user()->id)
            ->findOrFail($id);
        $row->increment('used_count');
        return response()->json(['ok' => true, 'used_count' => $row->fresh()->used_count]);
    }

    public function savedRepliesUpdate(Request $request, int $id): JsonResponse
    {
        $row = SavedReply::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        if ($row->user_id !== null && $row->user_id !== $request->user()->id) {
            if (!WorkspacePermissions::userCan($request->user(), 'savedreply.manage')) abort(403);
        }
        $data = $request->validate([
            'shortcut' => 'sometimes|string|max:64',
            'title'    => 'sometimes|string|max:128',
            'body'     => 'sometimes|string|max:4000',
            'category' => 'nullable|string|max:64',
        ]);
        if (isset($data['shortcut'])) {
            $data['shortcut'] = Str::slug($data['shortcut'], '_');
        }
        $row->update($data);
        return response()->json(['ok' => true, 'saved_reply' => $row->fresh()]);
    }

    public function savedRepliesDestroy(Request $request, int $id): JsonResponse
    {
        $row = SavedReply::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        if ($row->user_id !== null && $row->user_id !== $request->user()->id) {
            if (!WorkspacePermissions::userCan($request->user(), 'savedreply.manage')) abort(403);
        }
        $row->delete();
        return response()->json(['ok' => true]);
    }

    public function routingIndex(Request $request): JsonResponse
    {
        return response()->json(RoutingRule::forWorkspace($request->user()->current_workspace_id)->orderBy('sort')->get());
    }

    public function routingStore(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'routing.manage')) abort(403);

        // Plan: feature flag + numeric cap.
        $ws = $request->user()->currentWorkspace;
        \App\Services\PlanLimitGuard::feature($ws, 'access_routing_rules');
        \App\Services\PlanLimitGuard::check(
            $ws, 'routing_rules_limit',
            RoutingRule::where('workspace_id', $ws->id)->count(),
        );

        $data = $request->validate([
            'name'          => 'required|string|max:128',
            'conditions'    => 'required|array',
            'actions'       => 'required|array',
            'stop_on_match' => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
            'is_fallback'   => 'nullable|boolean',
            'sort'          => 'nullable|integer',
        ]);
        $rule = RoutingRule::create(array_merge($data, [
            'workspace_id' => $request->user()->current_workspace_id,
        ]));
        return response()->json(['ok' => true, 'rule' => $rule]);
    }

    public function routingUpdate(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'routing.manage')) abort(403);
        $rule = RoutingRule::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        $rule->update($request->only('name', 'conditions', 'actions', 'stop_on_match', 'is_active', 'is_fallback', 'sort'));
        return response()->json(['ok' => true, 'rule' => $rule->fresh()]);
    }

    public function routingDestroy(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'routing.manage')) abort(403);
        $rule = RoutingRule::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        $rule->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Kanban view — same data as the queue, rendered as 4 columns
     * (Open / Pending / Snoozed / Resolved) with drag-and-drop to change
     * inbox_status. Reuses /team-inbox/api/queue under the hood.
     */
    public function kanban()
    {
        // Same reset as the list view — kanban is the inbox too.
        if ($u = \Illuminate\Support\Facades\Auth::user()) {
            $u->forceFill(['inbox_last_seen_at' => now()])->save();
        }
        return view('user.team-inbox.kanban');
    }

    // ----------------------------------------------------------------
    // Outbound webhooks — CRM integration hooks
    // ----------------------------------------------------------------

    public function webhooksIndex(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $hooks = \App\Models\OutboundWebhook::query()
            ->forWorkspace($request->user()->current_workspace_id)
            ->orderByDesc('id')
            ->get(['id','name','url','events','is_active','fired_count','failed_count','last_fired_at','last_error']);
        return response()->json($hooks);
    }

    public function webhooksStore(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $data = $request->validate([
            'name'   => 'nullable|string|max:128',   // UI labels it optional
            'url'    => 'required|url|max:1024',
            'events' => 'required|array|min:1',
            'events.*' => 'string|max:64',
            'secret' => 'nullable|string|max:128',
            'is_active' => 'nullable|boolean',
        ]);
        // Name is optional in the UI — default to the endpoint host so the row
        // still has a readable label. (Previously 'required' → a blank name
        // 422'd and the save silently failed → "nothing saved / blank table".)
        $data['name'] = trim((string) ($data['name'] ?? '')) !== ''
            ? $data['name']
            : (parse_url($data['url'], PHP_URL_HOST) ?: 'Webhook');
        $hook = \App\Models\OutboundWebhook::create(array_merge($data, [
            'workspace_id' => $request->user()->current_workspace_id,
            'is_active'    => $data['is_active'] ?? true,
        ]));
        return response()->json(['ok' => true, 'webhook' => $hook]);
    }

    public function webhooksUpdate(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $hook = \App\Models\OutboundWebhook::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        $hook->update($request->only('name','url','events','secret','is_active'));
        return response()->json(['ok' => true, 'webhook' => $hook->fresh()]);
    }

    public function webhooksDestroy(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $hook = \App\Models\OutboundWebhook::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        $hook->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Business hours — workspace-level weekly schedule that the routing
     * engine consults via the `outside_business_hours` condition + via
     * the inline outside-hours auto-reply on save.
     */
    /**
     * #21 — "Add to contacts" from an inbound vCard. The inbound webhook
     * already stored the contact details on inbox_messages.meta.contact;
     * this endpoint promotes that captured vCard into a real Contact row
     * the operator can then target from campaigns / broadcasts.
     *
     * Idempotent — if a contact with the same mobile already exists for
     * this user we update its name (if missing) instead of duplicating.
     */
    public function extractMessageContact(Request $request, int $id): JsonResponse
    {
        $msg = InboxMessage::query()->findOrFail($id);
        // Workspace-scope the conversation lookup so we 404 cleanly
        // if the message belongs to another workspace's conversation,
        // even before the policy authorize() runs.
        $conv = $this->findConvInCurrentWorkspace($msg->conversation_id);
        $this->authorize('view', $conv);

        $card = is_array($msg->meta['contact'] ?? null) ? $msg->meta['contact'] : null;
        if (!$card || empty($card['phone']) && empty($card['name'])) {
            return response()->json(['ok' => false, 'message' => 'No contact card on this message.'], 422);
        }

        $userId = $conv->user_id ?: $request->user()->id;
        $wsId   = (int) ($conv->workspace_id ?: ($request->user()->current_workspace_id ?? 0));
        $rawPhone = preg_replace('/\D+/', '', (string) ($card['phone'] ?? ''));
        if ($rawPhone === '') {
            return response()->json(['ok' => false, 'message' => 'Contact has no phone number.'], 422);
        }
        // Split country code (heuristic: leading 1-3 digits if phone is 10+).
        $countryCode = '';
        $mobile = $rawPhone;
        if (strlen($rawPhone) > 10) {
            $countryCode = substr($rawPhone, 0, strlen($rawPhone) - 10);
            $mobile = substr($rawPhone, -10);
        }

        $name = trim((string) ($card['name'] ?? ''));
        [$first, $last] = $this->splitName($name);

        // Contact.mobile is encrypted at rest (non-deterministic ciphertext)
        // so we can't `where('mobile', X)` — we hydrate the workspace's
        // full contact list and compare decrypted values in PHP. Cheap
        // because the typical workspace has < 5k contacts; if that ever
        // balloons we'll add a deterministic mobile_hash column for
        // index lookups.
        $existing = \App\Models\Contact::query()
            ->where('workspace_id', $wsId)
            ->get()
            ->first(function ($c) use ($mobile) {
                return preg_replace('/\D+/', '', (string) $c->mobile) === $mobile;
            });
        if ($existing) {
            $updates = [];
            if (!$existing->name && $name !== '') $updates['name'] = $name;
            if (!$existing->first_name && $first !== '') $updates['first_name'] = $first;
            if (!$existing->last_name && $last !== '')   $updates['last_name'] = $last;
            if ($updates) $existing->update($updates);
            return response()->json(['ok' => true, 'contact_id' => $existing->id, 'created' => false]);
        }

        $c = \App\Models\Contact::create([
            'user_id'      => $userId,
            'workspace_id' => $wsId,
            'first_name'   => $first,
            'last_name'    => $last,
            'name'         => $name !== '' ? $name : $rawPhone,
            'country_code' => $countryCode,
            'mobile'       => $mobile,
        ]);

        AuditLogger::workspace('contact.created_from_message', $request->user()->id, $conv->workspace_id, 'contact', $c->id);
        return response()->json(['ok' => true, 'contact_id' => $c->id, 'created' => true]);
    }

    /** Split a "Firstname Lastname Whatever" string. Last word → last_name. */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name));
        if (!$parts || count($parts) === 0 || $parts[0] === '') return ['', ''];
        if (count($parts) === 1) return [$parts[0], ''];
        $last = array_pop($parts);
        return [implode(' ', $parts), $last];
    }

    public function businessHoursIndex(Request $request): JsonResponse
    {
        $ws = $request->user()->currentWorkspace;
        return response()->json([
            'timezone'       => $ws?->timezone ?: config('app.timezone', 'UTC'),
            'business_hours' => $ws?->business_hours ?: \App\Models\Workspace::defaultBusinessHours(),
        ]);
    }

    public function businessHoursUpdate(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'routing.manage')) abort(403);

        $days = \App\Models\Workspace::BUSINESS_HOURS_DAYS;
        $rules = [
            'timezone'                                => 'nullable|string|max:64|timezone',
            'business_hours'                          => 'required|array',
            'business_hours.days'                     => 'required|array',
            'business_hours.outside_action'           => 'required|in:none,template',
            'business_hours.outside_template_id'      => 'nullable|integer|exists:wa_templates,id',
            'business_hours.auto_reply_cooldown_min'  => 'nullable|integer|min:1|max:10080',  // up to 1 week
            'business_hours.spam_threshold_msgs'      => 'nullable|integer|min:2|max:500',
            'business_hours.spam_window_seconds'      => 'nullable|integer|min:5|max:3600',
        ];
        foreach ($days as $d) {
            $rules["business_hours.days.{$d}"]              = 'required|array';
            $rules["business_hours.days.{$d}.enabled"]      = 'required|boolean';
            $rules["business_hours.days.{$d}.from"]         = 'required|date_format:H:i';
            $rules["business_hours.days.{$d}.to"]           = 'required|date_format:H:i|after:business_hours.days.'.$d.'.from';
        }

        $data = $request->validate($rules);
        $ws = $request->user()->currentWorkspace;
        abort_unless($ws, 404);

        $updates = ['business_hours' => $data['business_hours']];
        // Only owners can rewrite the workspace timezone — mirrors the
        // /account profile-save rule. Non-owners just save their hours.
        if (!empty($data['timezone']) && (int) $ws->owner_user_id === (int) $request->user()->id) {
            $updates['timezone'] = $data['timezone'];
        }
        $ws->update($updates);

        return response()->json([
            'ok'             => true,
            'timezone'       => $ws->fresh()->timezone,
            'business_hours' => $ws->fresh()->business_hours,
        ]);
    }

    public function slaIndex(Request $request): JsonResponse
    {
        return response()->json(SlaPolicy::forWorkspace($request->user()->current_workspace_id)->get());
    }

    public function slaStore(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'sla.manage')) abort(403);
        $data = $request->validate([
            'name' => 'required|string|max:128',
            'first_response_minutes' => 'nullable|integer|min:1',
            'resolution_minutes' => 'nullable|integer|min:1',
            'pause_when_waiting_on_customer' => 'nullable|boolean',
            'respect_business_hours' => 'nullable|boolean',
            'priority_overrides' => 'nullable|array',
            'is_default' => 'nullable|boolean',
            'device_ids' => 'nullable|array',
            'device_ids.*' => 'integer|exists:devices,id',
        ]);
        $data['device_ids'] = $this->intersectWorkspaceDevices($request, $data['device_ids'] ?? []) ?: null;
        $policy = SlaPolicy::create(array_merge($data, [
            'workspace_id' => $request->user()->current_workspace_id,
        ]));
        if (!empty($data['is_default'])) {
            SlaPolicy::forWorkspace($request->user()->current_workspace_id)
                ->where('id', '!=', $policy->id)->update(['is_default' => false]);
        }
        return response()->json(['ok' => true, 'policy' => $policy->fresh()]);
    }

    public function slaUpdate(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'sla.manage')) abort(403);
        $policy = SlaPolicy::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        $data = $request->only('name', 'first_response_minutes', 'resolution_minutes',
            'pause_when_waiting_on_customer', 'respect_business_hours', 'priority_overrides', 'is_default');
        if ($request->has('device_ids')) {
            $data['device_ids'] = $this->intersectWorkspaceDevices($request, (array) $request->input('device_ids', [])) ?: null;
        }
        $policy->update($data);
        if ($request->boolean('is_default')) {
            SlaPolicy::forWorkspace($request->user()->current_workspace_id)
                ->where('id', '!=', $policy->id)->update(['is_default' => false]);
        }
        return response()->json(['ok' => true, 'policy' => $policy->fresh()]);
    }

    // ----------------------------------------------------------------
    // Agent status / availability
    // ----------------------------------------------------------------

    public function setStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status'  => 'required|in:' . implode(',', AgentStatus::STATUSES),
            'message' => 'nullable|string|max:128',
        ]);
        $row = AgentStatus::firstOrCreate(
            ['user_id' => $request->user()->id, 'workspace_id' => $request->user()->current_workspace_id],
            ['counters_date' => now()->toDateString()],
        );
        $row->forceFill([
            'status'         => $data['status'],
            'status_message' => $data['message'] ?? null,
            'last_seen_at'   => now(),
        ])->save();
        return response()->json(['ok' => true, 'status' => $row]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $list = InboxNotification::forUser($request->user()->id)
            ->forWorkspace($request->user()->current_workspace_id)
            ->orderByDesc('id')->limit(40)->get();
        return response()->json(['items' => $list, 'unread' => $list->whereNull('read_at')->count()]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        InboxNotification::forUser($request->user()->id)
            ->forWorkspace($request->user()->current_workspace_id)
            ->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    // ----------------------------------------------------------------
    // Team performance stats
    // ----------------------------------------------------------------

    // ----------------------------------------------------------------
    // Analytics pages — full /team-inbox/analytics/* surface
    // ----------------------------------------------------------------

    public function teamAnalyticsPage()    { return view('user.team-inbox.analytics-team'); }
    public function aiAnalyticsPage()      { return view('user.team-inbox.analytics-ai'); }

    /**
     * Team Performance — per-user metrics over today/week/month windows.
     * Powers /team-inbox/analytics/team. Manager+ only.
     */
    public function teamAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $user->current_workspace_id;
        $range = (string) $request->query('range', 'today'); // today | week | month
        $since = match ($range) {
            'week'  => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };
        // Multi-device filter. When set, every aggregate below narrows
        // to conversations whose device_id matches — this lets owners
        // compare "kapil number's volume" vs "himanshu number's
        // volume" without exporting raw data. Null/empty = workspace-
        // wide (same numbers single-device installs always saw).
        $deviceId = $request->query('device_id') ? (int) $request->query('device_id') : null;

        // Outbound messages per user in range — counts the operator's
        // workload. Joins to conversation for workspace gate.
        $perUser = \DB::table('inbox_messages')
            ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
            ->join('users', 'users.id', '=', 'inbox_messages.user_id')
            ->where('conversations.workspace_id', $wsId)
            ->when($deviceId, fn ($q) => $q->where('conversations.device_id', $deviceId))
            ->where('inbox_messages.direction', 'out')
            ->where('inbox_messages.created_at', '>=', $since)
            ->whereNull('inbox_messages.agent_id') // exclude AI-agent replies
            ->groupBy('users.id', 'users.name')
            ->select('users.id', 'users.name', \DB::raw('COUNT(*) as replies'))
            ->orderByDesc('replies')
            ->get();

        // Resolutions per user in range.
        $perUserResolved = \DB::table('conversations')
            ->join('users', 'users.id', '=', 'conversations.resolved_by')
            ->where('conversations.workspace_id', $wsId)
            ->when($deviceId, fn ($q) => $q->where('conversations.device_id', $deviceId))
            ->where('conversations.inbox_status', 'resolved')
            ->where('conversations.resolved_at', '>=', $since)
            ->whereNotNull('conversations.resolved_by')
            ->groupBy('users.id', 'users.name')
            ->select('users.id', \DB::raw('COUNT(*) as resolved'))
            ->pluck('resolved', 'id');

        // Average first-response minutes per user.
        $avgFirstResponse = \DB::table('conversations')
            ->where('workspace_id', $wsId)
            ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
            ->where('resolved_at', '>=', $since)
            ->whereNotNull('first_response_at')
            ->whereNotNull('assignee_user_id')
            ->groupBy('assignee_user_id')
            ->select('assignee_user_id', \DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_min'))
            ->pluck('avg_min', 'assignee_user_id');

        // Current statuses
        $statuses = AgentStatus::forWorkspace($wsId)->get()->keyBy('user_id');

        $agents = $perUser->map(function ($row) use ($perUserResolved, $avgFirstResponse, $statuses) {
            $s = $statuses->get($row->id);
            $afr = $avgFirstResponse->get($row->id);
            return [
                'user_id'         => $row->id,
                'name'            => $row->name,
                'replies'         => (int) $row->replies,
                'resolved'        => (int) ($perUserResolved->get($row->id) ?? 0),
                'avg_response_min'=> $afr !== null ? (int) round((float) $afr) : null,
                'status'          => $s?->status ?? 'offline',
                'current_load'    => $s?->current_load ?? 0,
                'last_seen_at'    => $s?->last_seen_at,
            ];
        })->values();

        // Top-line workspace metrics for the hero band.
        $totalReplies = (int) \DB::table('inbox_messages')
            ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
            ->where('conversations.workspace_id', $wsId)
            ->when($deviceId, fn ($q) => $q->where('conversations.device_id', $deviceId))
            ->where('inbox_messages.direction', 'out')
            ->where('inbox_messages.created_at', '>=', $since)
            ->whereNull('inbox_messages.agent_id')
            ->count();
        $totalResolved = (int) \DB::table('conversations')
            ->where('workspace_id', $wsId)
            ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
            ->where('inbox_status', 'resolved')
            ->where('resolved_at', '>=', $since)
            ->count();
        $totalInbounds = (int) \DB::table('inbox_messages')
            ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
            ->where('conversations.workspace_id', $wsId)
            ->when($deviceId, fn ($q) => $q->where('conversations.device_id', $deviceId))
            ->where('inbox_messages.direction', 'in')
            ->where('inbox_messages.created_at', '>=', $since)
            ->count();
        $wsAvgFirstResp = \DB::table('conversations')
            ->where('workspace_id', $wsId)
            ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
            ->where('resolved_at', '>=', $since)
            ->whereNotNull('first_response_at')
            ->avg(\DB::raw('TIMESTAMPDIFF(MINUTE, created_at, first_response_at)'));

        return response()->json([
            'range'   => $range,
            'since'   => $since->toIso8601String(),
            'totals'  => [
                'replies'              => $totalReplies,
                'resolved'             => $totalResolved,
                'inbounds'             => $totalInbounds,
                'avg_first_response'   => $wsAvgFirstResp !== null ? (int) round((float) $wsAvgFirstResp) : null,
            ],
            'agents'  => $agents,
        ]);
    }

    /**
     * AI Agent Performance — per-AI-agent metrics with self-rating.
     * Powers /team-inbox/analytics/ai-agents.
     */
    public function aiAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $user->current_workspace_id;
        $range = (string) $request->query('range', 'today');
        $since = match ($range) {
            'week'  => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };
        // Same multi-device filter shape as teamAnalytics — null skips
        // narrowing entirely, preserving the workspace-wide totals
        // single-device installs always saw.
        $deviceId = $request->query('device_id') ? (int) $request->query('device_id') : null;

        $agents = AiAgent::forWorkspace($wsId)->orderBy('id')->get();
        $rows = $agents->map(function ($a) use ($wsId, $since, $deviceId) {
            $base = \DB::table('inbox_messages')
                ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
                ->where('conversations.workspace_id', $wsId)
                ->when($deviceId, fn ($q) => $q->where('conversations.device_id', $deviceId))
                ->where('inbox_messages.agent_id', $a->id)
                ->where('inbox_messages.created_at', '>=', $since);

            $sent     = (int) (clone $base)->count();
            $failed   = (int) (clone $base)->where('inbox_messages.status', 'failed')->count();
            $convs    = (int) (clone $base)->distinct('inbox_messages.conversation_id')->count('inbox_messages.conversation_id');
            $avgScore = (clone $base)->whereNotNull('inbox_messages.quality_score')->avg('inbox_messages.quality_score');
            $scoreCnt = (int) (clone $base)->whereNotNull('inbox_messages.quality_score')->count();

            // Active assignments right now (across all time, not range-bounded).
            $activeConvs = (int) \DB::table('conversations')
                ->where('workspace_id', $wsId)
                ->when($deviceId, fn ($q) => $q->where('device_id', $deviceId))
                ->where('assignee_agent_id', $a->id)
                ->whereIn('inbox_status', ['open', 'pending'])
                ->count();

            return [
                'id'             => $a->id,
                'name'           => $a->name,
                'provider'       => $a->provider,
                'model'          => $a->model,
                'tone'           => $a->tone,
                'avatar_color'   => $a->avatar_color,
                'is_active'      => (bool) $a->is_active,
                'messages_sent'  => $sent,
                'messages_failed'=> $failed,
                'success_rate'   => $sent > 0 ? round((($sent - $failed) / $sent) * 100, 1) : null,
                'conversations'  => $convs,
                'active_now'     => $activeConvs,
                'avg_quality'    => $avgScore !== null ? round((float) $avgScore, 1) : null,
                'rated_count'    => $scoreCnt,
                'lifetime_sent'  => (int) ($a->messages_sent ?? 0),
            ];
        })->values();

        // Top-3 most recent self-rated replies (sample bubbles for the page).
        $recentRated = \DB::table('inbox_messages')
            ->join('conversations', 'conversations.id', '=', 'inbox_messages.conversation_id')
            ->where('conversations.workspace_id', $wsId)
            ->when($deviceId, fn ($q) => $q->where('conversations.device_id', $deviceId))
            ->whereNotNull('inbox_messages.agent_id')
            ->whereNotNull('inbox_messages.quality_score')
            ->orderByDesc('inbox_messages.id')
            ->limit(10)
            ->select(
                'inbox_messages.id', 'inbox_messages.agent_id', 'inbox_messages.quality_score',
                'inbox_messages.quality_note', 'inbox_messages.created_at', 'inbox_messages.conversation_id'
            )
            ->get()
            ->map(function ($r) use ($agents) {
                $a = $agents->firstWhere('id', $r->agent_id);
                return [
                    'id'             => $r->id,
                    'agent_name'     => optional($a)->name ?? '—',
                    'agent_color'    => optional($a)->avatar_color ?? '#6366f1',
                    'conversation_id'=> $r->conversation_id,
                    'score'          => (int) $r->quality_score,
                    'note'           => $r->quality_note,
                    'created_at'     => $r->created_at,
                ];
            });

        return response()->json([
            'range'  => $range,
            'since'  => $since->toIso8601String(),
            'agents' => $rows,
            'recent_rated' => $recentRated,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $wsId = (int) $user->current_workspace_id;

        $base = Conversation::query()->forWorkspace($wsId);

        $totalOpen        = (clone $base)->open()->count();
        $awaitingReply    = (clone $base)->open()->whereNull('first_response_at')->count();
        $unassigned       = (clone $base)->open()->unassigned()->count();
        $slaBreached      = (clone $base)->open()->where('sla_breached', true)->count();
        $resolvedToday    = (clone $base)
            ->where('inbox_status', 'resolved')
            ->whereDate('resolved_at', now()->toDateString())
            ->count();

        $avgFirstResponse = Conversation::query()
            ->forWorkspace($wsId)
            ->where('inbox_status', 'resolved')
            ->where('resolved_at', '>=', now()->subDays(7))
            ->whereNotNull('first_response_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_min')
            ->value('avg_min');

        $statuses = AgentStatus::forWorkspace($wsId)->with('user:id,name')->get();
        $agents = $statuses->map(function ($s) {
            $s->rolloverIfStale();
            return [
                'user_id'           => $s->user_id,
                'name'              => optional($s->user)->name ?? 'Unknown',
                'status'            => $s->status,
                'current_load'      => $s->current_load,
                'today_replies'     => $s->today_replies,
                'today_resolutions' => $s->today_resolutions,
                'last_seen_at'      => $s->last_seen_at,
            ];
        })->values();

        return response()->json([
            'queue' => [
                'open'           => $totalOpen,
                'awaiting_reply' => $awaitingReply,
                'unassigned'     => $unassigned,
                'sla_breached'   => $slaBreached,
                'resolved_today' => $resolvedToday,
            ],
            'avg_first_response_minutes' => $avgFirstResponse !== null ? (int) round((float) $avgFirstResponse) : null,
            'agents'       => $agents,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    // ----------------------------------------------------------------
    // Serializers
    // ----------------------------------------------------------------

    private function serializeListItem(Conversation $c, array $agentMap = [], array $deviceMap = []): array
    {
        $agent = $c->assignee_agent_id
            ? ($agentMap[$c->assignee_agent_id] ?? AiAgent::find($c->assignee_agent_id))
            : null;

        // Pre-batched device lookup (keyed by id) keeps the queue
        // serializer N+1-free even when the page has rows spanning
        // every paired device on the workspace.
        $device = ($c->device_id && isset($deviceMap[$c->device_id]))
            ? $deviceMap[$c->device_id]
            : ($c->device_id ? \App\Models\Device::find($c->device_id) : null);

        // WhatsApp calling availability per conversation.
        //
        // Three gates, ALL must pass — calling is WABA-only and only
        // valid against a real 1-on-1 chat row (not a campaign-template
        // row that lives in the same table):
        //   1. Workspace has a WABA provider config with calling_enabled
        //   2. This is a WABA conversation (provider === 'waba'). NOTE: we used to
        //      test `device_id === null`, but since the device-unification fix a
        //      WABA thread carries the wa_provider_configs id in device_id (not
        //      null), so that gate ALWAYS failed and the call icon never showed.
        //      Key off the provider instead (Baileys/Twilio can't WABA-call).
        //   3. raw_jid is a phone-shape JID (not a campaign row that
        //      shares this table with device_id=null + raw_jid=null)
        $rawJid = (string) ($c->raw_jid ?? '');
        // A real phone conversation, NOT a campaign/template row (those have
        // raw_jid=null). Accept BOTH shapes: Baileys stores a JID ("…@s.whatsapp.net"),
        // but WABA stores bare digits (raw_jid = preg_replace('/\D+/','',from) —
        // see WaWebhookController:718), so a `\d+@`-only test hid the call button on
        // every WABA chat.
        $hasPhoneJid = $rawJid !== ''
            && (preg_match('/^\d+@/', $rawJid) === 1 || preg_match('/^\d{6,15}$/', $rawJid) === 1);
        // WABA-callable when: real phone JID + workspace has WABA calling enabled +
        // it's not a GENUINE Baileys chat. NOTE: migration 2026_05_26_140000
        // backfilled every pre-existing conversation to provider='baileys', so an
        // old WABA thread can carry provider='baileys' even though it's WABA — a
        // strict `provider==='waba'` check hid the call button on all of them. So
        // we also allow provider!='waba' when the workspace has NO Baileys device
        // at all (then 'baileys' can only be a backfill artifact = really WABA).
        $waCallingOn = $hasPhoneJid
            && $this->workspaceCallingEnabled((int) $c->workspace_id)
            && ($c->provider === 'waba' || ! $this->workspaceHasBaileys((int) $c->workspace_id));

        return [
            'id'                => $c->id,
            // Customer phone numbers are masked everywhere they're displayed —
            // the title is usually "Name · +number" or a bare "+number".
            'title'             => mask_phone((string) $c->title),
            'preview'           => $c->preview,
            'inbox_status'      => $c->inbox_status,
            'priority'          => $c->priority,
            'channel'           => $c->channel,
            'assignee_user_id'  => $c->assignee_user_id,
            'assignee_name'     => $c->relationLoaded('assignee') ? optional($c->assignee)->name : null,
            'assignee_team_id'  => $c->assignee_team_id,
            'team_name'         => $c->relationLoaded('team')     ? optional($c->team)->name     : null,
            'team_color'        => $c->relationLoaded('team')     ? optional($c->team)->color    : null,
            'assignee_agent_id' => $c->assignee_agent_id,
            'agent_name'        => optional($agent)->name,
            'agent_color'       => optional($agent)->avatar_color,
            'last_message_at'   => $c->last_message_at,
            'snoozed_until'     => $c->snoozed_until,
            'sla_breached'      => (bool) $c->sla_breached,
            'sla_first_due'     => $c->sla_first_response_due,
            'sla_resolution_due'=> $c->sla_resolution_due,
            'first_response_at' => $c->first_response_at,
            'unread_count'      => $c->unread_count,
            'is_spam'           => (bool) $c->is_spam,
            // WhatsApp-style list flags + labels (see queue() eager-load).
            'archived'          => (bool) $c->archived,
            'pinned'            => $c->pinned_at !== null,
            'muted'             => $c->muted_at !== null,
            'tags'              => $c->relationLoaded('tags')
                                    ? $c->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                                    : [],
            // Customer's WhatsApp @username (Meta 2026 rollout) captured from the
            // inbound webhook — shown beside the number when present.
            'wa_username'       => is_array($c->routing_meta) ? ($c->routing_meta['wa_username'] ?? null) : null,
            'device_id'         => $c->device_id,
            'device_label'      => $device ? (trim((string) $device->device_name) ?: ('Device #' . $device->id)) : null,
            'device_phone'      => $device ? trim('+' . ltrim((string) $device->country_code, '+') . ' ' . $device->phone_number) : null,
            // Connection state — shipped per-conversation so the left rail
            // card + thread header can show a green/red dot without a
            // separate /devices fetch. WABA / Twilio run over the Cloud API
            // (no paired phone to "connect"), so they report 'cloud' — the UI
            // hides the pill entirely. Resolve by PROVIDER first: since the
            // device_id-unification fix, a WABA thread carries the
            // wa_provider_configs id (not null and not a Baileys `devices`
            // row), so the old `device_id === null ? cloud : unknown` fell
            // through to 'unknown' and the pill was stuck on "Connecting…".
            'device_status'     => in_array($c->provider, ['waba', 'twilio'], true)
                                    ? 'cloud'
                                    : ($device ? (string) ($device->status ?? 'disconnected')
                                                : ($c->device_id === null ? 'cloud' : 'unknown')),
            'device_last_seen'  => $device && $device->last_seen_at ? $device->last_seen_at->toIso8601String() : null,
            'wa_calling_enabled'=> $waCallingOn,
        ];
    }

    /**
     * Per-workspace cache of "is calling enabled on this workspace's
     * WABA number". Static + request-scoped so a 50-row queue serializer
     * resolves to ONE SELECT instead of fifty. Returns false for
     * Baileys-only workspaces (no WABA config at all).
     */
    private function workspaceCallingEnabled(int $workspaceId): bool
    {
        static $cache = [];
        if (array_key_exists($workspaceId, $cache)) return $cache[$workspaceId];

        $cfg = \App\Models\WaProviderConfig::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'waba')
            ->first(['calling_enabled']);

        return $cache[$workspaceId] = (bool) ($cfg?->calling_enabled);
    }

    /**
     * Does this workspace actually run Baileys (the Unofficial API)? Used to tell
     * a GENUINE Baileys conversation apart from a WABA thread that migration
     * 2026_05_26_140000 backfilled to provider='baileys'. If the workspace has no
     * Baileys device at all, a 'baileys'-stamped thread can only be that backfill
     * artifact — i.e. really a WABA chat — so calling is allowed on it. Static +
     * request-scoped so the queue serializer resolves it once per workspace.
     */
    private function workspaceHasBaileys(int $workspaceId): bool
    {
        static $cache = [];
        if (array_key_exists($workspaceId, $cache)) return $cache[$workspaceId];

        return $cache[$workspaceId] = \App\Models\Device::query()
            ->where('workspace_id', $workspaceId)
            ->exists();
    }

    private function serializeFull(Conversation $c, $tags): array
    {
        $resolvedByName = null;
        if ($c->resolved_by) {
            $resolvedByName = optional(User::find($c->resolved_by))->name;
        }
        $resolvedByAgentName = null;
        if ($c->resolved_by_agent_id) {
            $resolvedByAgentName = optional(AiAgent::find($c->resolved_by_agent_id))->name;
        }

        // Contact-phone + WABA calling permission. The conversations
        // table doesn't have a contact_id FK — phones are stored on
        // raw_jid (digits before "@") for WhatsApp threads. Fall back
        // to the encrypted title (typically "Name · +<E164>") if
        // raw_jid is empty.
        $contactPhone = null;
        $callingPermission = null; // 'granted' | 'expired' | 'declined' | null
        $rawJid = (string) ($c->raw_jid ?? '');
        if (preg_match('/^(\d+)@/', $rawJid, $m)) {
            $contactPhone = $m[1];                       // Baileys JID form
        } elseif (preg_match('/^(\d{6,15})$/', $rawJid, $m)) {
            $contactPhone = $m[1];                       // WABA stores bare digits
        } elseif ($c->title && preg_match('/\+?(\d{6,15})/', (string) $c->title, $m)) {
            $contactPhone = $m[1];
        }
        if ($contactPhone) {
            $cfg = \App\Models\WaProviderConfig::where('workspace_id', $c->workspace_id)
                ->where('provider', 'waba')->first(['id']);
            if ($cfg) {
                $perm = \App\Models\WaCallPermission::where('wa_provider_config_id', $cfg->id)
                    ->where('contact_phone', $contactPhone)->first();
                if ($perm) {
                    $callingPermission = $perm->isUsable() ? 'granted' : $perm->status;
                }
            }
        }

        return array_merge($this->serializeListItem($c), [
            'tags'                   => $tags,
            'resolved_at'            => $c->resolved_at,
            'resolved_by'            => $c->resolved_by,
            'resolved_by_name'       => $resolvedByName,
            'resolved_by_agent_id'   => $c->resolved_by_agent_id,
            'resolved_by_agent_name' => $resolvedByAgentName,
            // Raw number is kept ONLY for the WABA dialer (it dials by digits);
            // every UI text render uses the masked display copy.
            'contact_phone'          => $contactPhone,
            'contact_phone_display'  => mask_phone((string) $contactPhone),
            'wa_calling_permission'  => $callingPermission,
        ]);
    }

    private function serializeMessage(InboxMessage $m, array $agentMap = []): array
    {
        $agent = $m->agent_id
            ? ($agentMap[$m->agent_id] ?? AiAgent::find($m->agent_id))
            : null;

        // Pass header / footer / buttons from meta through to the
        // renderer so an outbound template message displays the full
        // WhatsApp-style bubble (heading, body, footer, button rows)
        // instead of just the body text.
        $meta    = is_array($m->meta) ? $m->meta : [];
        $header  = (string) ($meta['header']  ?? '');
        $footer  = (string) ($meta['footer']  ?? '');
        $buttons = isset($meta['buttons']) && is_array($meta['buttons']) ? $meta['buttons'] : [];
        $contact = isset($meta['contact']) && is_array($meta['contact']) ? $meta['contact'] : null;
        $ptt     = !empty($meta['ptt']);
        $forwarded            = !empty($meta['forwarded']);
        $frequentlyForwarded  = !empty($meta['frequently_forwarded']);

        // Catalog send — surface a tidy payload the JS renderer uses
        // to draw a product-tile bubble instead of a plain text body.
        $catalog = null;
        if (($meta['kind'] ?? null) === 'catalog') {
            $catalog = [
                'mode'     => $meta['mode'] ?? 'spm',
                'provider' => $meta['provider'] ?? ($meta['baileys'] ?? false ? 'baileys' : 'waba'),
                'products' => is_array($meta['products'] ?? null) ? $meta['products'] : [],
            ];
        }

        // Location share — pulled from top-level lat/lng columns, with
        // optional name/address from meta. Renderer uses these to draw
        // a map-pin tile + an "Open in Google Maps" deep-link.
        $location = null;
        $latRaw = $m->latitude  ?? ($meta['location_latitude']  ?? null);
        $lngRaw = $m->longitude ?? ($meta['location_longitude'] ?? null);
        if ($latRaw !== null && $lngRaw !== null) {
            $location = [
                'lat'     => (float) $latRaw,
                'lng'     => (float) $lngRaw,
                'name'    => $meta['location_name']    ?? null,
                'address' => $meta['location_address'] ?? null,
            ];
        }

        return [
            'id'          => $m->id,
            'direction'   => $m->direction,
            'body'        => $m->body,
            // Real-time translation: `translated_body` is the agent-language
            // view; the JS shows it with a "translated from X" badge + a toggle
            // back to the original `body`.
            'translated_body'   => $m->translated_body ?: null,
            'detected_language' => $m->detected_language ?: null,
            'is_translated'     => (bool) $m->is_translated,
            'media_path'  => $m->media_path,
            'media_url'   => $m->media_path ? media_url($m->media_path) : null,
            'media_name'  => $m->media_path ? $this->originalMediaFilename($m->media_path) : null,
            'media_type'  => $m->media_type,
            'reaction'    => $m->reaction ?: null,
            'pinned'      => (bool) $m->pinned,
            'starred'     => (bool) $m->starred,
            'quality_score' => $m->quality_score !== null ? (int) $m->quality_score : null,
            'quality_note'  => $m->quality_note,
            'header'      => $header !== '' ? $header : null,
            'footer'      => $footer !== '' ? $footer : null,
            'buttons'     => $buttons,
            // WhatsApp Flow form submission — the inbox renders this as a
            // clickable "Form response" card that opens a detail panel with
            // every field the customer answered.
            'form'        => (($meta['type'] ?? null) === 'wa_form_submission') ? [
                'kind'          => 'submission',
                'title'         => (string) ($meta['form_title'] ?? 'Form'),
                'submission_id' => $meta['submission_id'] ?? null,
                'fields'        => is_array($meta['fields'] ?? null) ? array_values($meta['fields']) : [],
            ] : null,
            'contact'     => $contact,
            'ptt'         => $ptt,
            'forwarded'             => $forwarded,
            'frequently_forwarded'  => $frequentlyForwarded,
            'catalog'    => $catalog,
            'location'   => $location,
            // Voice-call entry — renderer draws a WhatsApp-style call bubble
            // ("Voice call · 4 min" / "Missed voice call") from these fields.
            'call'       => $m->media_type === 'call' ? [
                'status'    => (string) ($meta['call_status'] ?? ''),
                'direction' => (string) ($meta['call_direction'] ?? $m->direction),
                'duration'  => (int) ($meta['duration_sec'] ?? 0),
            ] : null,
            'status'      => $m->status,
            // Surface WHY a send failed so the operator sees the real reason
            // (e.g. "outside 24h window — use a template", "no connected
            // device") instead of a blank "failed · retry".
            'failure_reason' => $m->status === 'failed' ? ($m->failure_reason ?: null) : null,
            'user_id'     => $m->user_id,
            'agent_id'    => $m->agent_id,
            'agent_name'  => optional($agent)->name,
            'agent_color' => optional($agent)->avatar_color,
            'created_at'  => $m->created_at,
            'sent_at'     => $m->sent_at,
            'delivered_at'=> $m->delivered_at,
            'read_at'     => $m->read_at,
            'edited_at'   => $m->edited_at,
            'editable'    => $m->isEditable(),
            'time_label'  => $m->display_time,
        ];
    }

    /**
     * Files saved by the inbound bridge use the convention
     * "chat-media/<random>__<originalName>" — recover the original
     * filename for download links + the doc tile label.
     */
    private function originalMediaFilename(string $path): string
    {
        $base = basename($path);
        return str_contains($base, '__') ? substr($base, strpos($base, '__') + 2) : $base;
    }

    private function serializeNote(ConversationNote $n): array
    {
        return [
            'id'         => $n->id,
            'body'       => $n->body,
            'mentions'   => $n->mentions ?? [],
            'is_pinned'  => (bool) $n->is_pinned,
            'author_id'  => $n->user_id,
            'author_name'=> optional($n->author)->name,
            'created_at' => $n->created_at,
            'edited_at'  => $n->edited_at,
        ];
    }

    private function serializeEvent(ConversationEvent $e): array
    {
        $payload = is_array($e->payload) ? $e->payload : [];
        return [
            'id'           => $e->id,
            'type'         => $e->type,
            'actor_user_id'=> $e->actor_user_id,
            'actor_name'   => optional($e->actor)->name ?? ($payload['by'] ?? null),
            'agent_id'     => $payload['agent_id'] ?? null,
            'agent_name'   => $payload['agent_name'] ?? null,
            'payload'      => $payload,
            'created_at'   => $e->created_at,
        ];
    }

    // ----------------------------------------------------------------
    // AI Agents CRUD
    // ----------------------------------------------------------------

    public function aiAgentsIndex(Request $request): JsonResponse
    {
        $wsId = $request->user()->current_workspace_id;
        return response()->json(AiAgent::forWorkspace($wsId)->orderBy('id')->get()->map->toCard()->values());
    }

    public function aiAgentsStore(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        // Plan: feature flag + numeric cap.
        $ws = $request->user()->currentWorkspace;
        \App\Services\PlanLimitGuard::feature($ws, 'access_ai_agents');
        \App\Services\PlanLimitGuard::check(
            $ws, 'ai_agents_limit',
            AiAgent::where('workspace_id', $ws->id)->count(),
        );

        // Never hard-block a save on a blank/absent model — the modal's model
        // <select> can arrive empty (provider not in the JS map, or the saved
        // model isn't among the rebuilt options), which sent model:null and
        // tripped "The model field must be a string." Fall back to the
        // provider's default so the agent always persists with a usable model.
        $this->coerceAgentModel($request);
        $data = $request->validate([
            'name'          => 'required|string|max:191',
            'provider'      => 'required|in:openai,anthropic,gemini',
            'model'         => 'required|string|max:64',
            'system_prompt' => 'nullable|string|max:4000',
            'tone'          => 'nullable|in:friendly,professional,concise,empathetic',
            'avatar_color'  => 'nullable|string|max:7',
            'auto_respond'  => 'nullable|boolean',
            'max_tokens'    => 'nullable|integer|min:64|max:4096',
            'temperature'   => 'nullable|integer|min:0|max:10',
            'is_active'     => 'nullable|boolean',
            // Handoff settings.
            'max_replies_per_conversation' => 'nullable|integer|min:0|max:200',
            'handoff_keywords'             => 'nullable|array',
            'handoff_keywords.*'           => 'string|max:64',
            'handoff_low_score_threshold'  => 'nullable|integer|min:0|max:10',
            'handoff_low_score_window'     => 'nullable|integer|min:1|max:10',
            'handoff_enabled'              => 'nullable|boolean',
            'use_saved_replies'            => 'nullable|boolean',
            // Multi-device scoping. Empty / omitted = any device.
            'device_ids'                   => 'nullable|array',
            'device_ids.*'                 => 'integer|exists:devices,id',
            // Voice-AI config. All optional — agent defaults to text-only
            // until the operator opts in via the Voice tab.
            'voice_note_enabled'      => 'nullable|boolean',
            'voice_call_enabled'      => 'nullable|boolean',
            'voice_provider'          => 'nullable|in:openai,elevenlabs',
            'voice_id'                => 'nullable|string|max:96',
            'voice_language'          => 'nullable|string|max:8',
            'max_voice_notes_per_day' => 'nullable|integer|min:0|max:10000',
        ]);
        // Intersect with workspace-owned devices so a forged payload
        // can't smuggle in another workspace's device id.
        if (!empty($data['device_ids'])) {
            $owned = \App\Models\Device::query()
                ->whereIn('user_id', \App\Models\User::query()
                    ->where('current_workspace_id', $request->user()->current_workspace_id)
                    ->pluck('id'))
                ->whereIn('id', $data['device_ids'])
                ->pluck('id')
                ->all();
            $data['device_ids'] = array_values(array_map('intval', $owned));
        }
        $agent = AiAgent::create(array_merge($data, [
            'workspace_id' => $request->user()->current_workspace_id,
            'tone'         => $data['tone'] ?? 'professional',
            'avatar_color' => $data['avatar_color'] ?? '#6366f1',
            'auto_respond' => $data['auto_respond'] ?? true,
            'max_tokens'   => $data['max_tokens'] ?? 512,
            'temperature'  => $data['temperature'] ?? 7,
            'is_active'    => $data['is_active'] ?? true,
            'max_replies_per_conversation' => $data['max_replies_per_conversation'] ?? 10,
            'handoff_low_score_window'     => $data['handoff_low_score_window'] ?? 3,
            'handoff_enabled'              => $data['handoff_enabled'] ?? true,
        ]));
        return response()->json(['ok' => true, 'agent' => $agent->toCard()], 201);
    }

    public function aiAgentsUpdate(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $agent = AiAgent::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        // A blank/null model on PATCH would fail `sometimes|string` with "must
        // be a string" and block the whole save (incl. voice-tab edits). Keep
        // the agent's existing model when the form sends a blank one; only fall
        // back to a provider default if there's nothing saved either.
        $this->coerceAgentModel($request, $agent->model);
        $data = $request->validate([
            'name'          => 'sometimes|string|max:191',
            'provider'      => 'sometimes|in:openai,anthropic,gemini',
            'model'         => 'sometimes|string|max:64',
            'system_prompt' => 'nullable|string|max:4000',
            'tone'          => 'nullable|in:friendly,professional,concise,empathetic',
            'avatar_color'  => 'nullable|string|max:7',
            'auto_respond'  => 'nullable|boolean',
            'max_tokens'    => 'nullable|integer|min:64|max:4096',
            'temperature'   => 'nullable|integer|min:0|max:10',
            'is_active'     => 'nullable|boolean',
            'max_replies_per_conversation' => 'nullable|integer|min:0|max:200',
            'handoff_keywords'             => 'nullable|array',
            'handoff_keywords.*'           => 'string|max:64',
            'handoff_low_score_threshold'  => 'nullable|integer|min:0|max:10',
            'handoff_low_score_window'     => 'nullable|integer|min:1|max:10',
            'handoff_enabled'              => 'nullable|boolean',
            'use_saved_replies'            => 'nullable|boolean',
            'device_ids'                   => 'nullable|array',
            'device_ids.*'                 => 'integer|exists:devices,id',
            // Voice-AI config — same rules as the Store endpoint.
            'voice_note_enabled'      => 'nullable|boolean',
            'voice_call_enabled'      => 'nullable|boolean',
            'voice_provider'          => 'nullable|in:openai,elevenlabs',
            'voice_id'                => 'nullable|string|max:96',
            'voice_language'          => 'nullable|string|max:8',
            'max_voice_notes_per_day' => 'nullable|integer|min:0|max:10000',
        ]);
        if (array_key_exists('device_ids', $data) && !empty($data['device_ids'])) {
            $owned = \App\Models\Device::query()
                ->whereIn('user_id', \App\Models\User::query()
                    ->where('current_workspace_id', $request->user()->current_workspace_id)
                    ->pluck('id'))
                ->whereIn('id', $data['device_ids'])
                ->pluck('id')
                ->all();
            $data['device_ids'] = array_values(array_map('intval', $owned));
        }
        $agent->update($data);
        return response()->json(['ok' => true, 'agent' => $agent->fresh()->toCard()]);
    }

    public function aiAgentsDestroy(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $agent = AiAgent::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        // Unassign from all active conversations before deleting.
        Conversation::forWorkspace($request->user()->current_workspace_id)
            ->where('assignee_agent_id', $agent->id)
            ->update(['assignee_agent_id' => null]);
        $agent->delete();
        return response()->json(['ok' => true]);
    }

    public function aiAgentsTestReply(Request $request, int $id): JsonResponse
    {
        $agent = AiAgent::forWorkspace($request->user()->current_workspace_id)->findOrFail($id);
        $data  = $request->validate(['message' => 'required|string|max:1000']);

        $svc   = app(\App\Services\AiAgentService::class);
        $reply = $svc->callProvider(
            provider:     $agent->provider,
            model:        $agent->model,
            workspaceId:  $request->user()->current_workspace_id,
            systemPrompt: ($agent->system_prompt ?: 'You are a helpful WhatsApp assistant.'),
            userPrompt:   $data['message'],
            maxTokens:    $agent->max_tokens,
            temperature:  $agent->temperatureFloat(),
        );

        return response()->json([
            'ok'    => true,
            'reply' => $reply ?? '[no response — check your API key]',
        ]);
    }

    /**
     * Guarantee the agent-save request carries a usable `model` string.
     *
     * The team-inbox modal's model <select> can submit `null`/empty when the
     * agent's provider isn't in the client's model map, or the saved model
     * isn't among the options it rebuilt on edit. That null tripped the
     * `string` validation ("The model field must be a string.") and blocked
     * the entire save — including unrelated voice-tab changes. When the model
     * arrives blank we substitute, in order: the caller-supplied fallback
     * (the agent's currently-saved model on update), then the provider's
     * default. Absent entirely (a partial PATCH that never mentions model),
     * we leave it so `sometimes` skips it and the saved value stands.
     */
    private function coerceAgentModel(Request $request, ?string $fallback = null): void
    {
        if (!$request->has('model')) return; // omitted → don't touch
        $model = $request->input('model');
        if (is_string($model) && trim($model) !== '') return; // already valid

        $defaults = [
            'openai'    => 'gpt-4o-mini',
            'anthropic' => 'claude-haiku-4-5-20251001',
            'gemini'    => 'gemini-2.5-flash-lite',
        ];
        $provider = (string) $request->input('provider', 'openai');
        $resolved = ($fallback !== null && trim((string) $fallback) !== '')
            ? (string) $fallback
            : ($defaults[$provider] ?? 'gpt-4o-mini');
        $request->merge(['model' => $resolved]);
    }

    // ----------------------------------------------------------------
    // AI Provider Keys (per workspace)
    // ----------------------------------------------------------------

    public function aiKeysIndex(Request $request): JsonResponse
    {
        $wsId = $request->user()->current_workspace_id;
        return response()->json(
            AiProviderKey::query()
                ->where('workspace_id', $wsId)
                ->get(['id', 'provider', 'is_active'])
                ->map(fn ($k) => ['id' => $k->id, 'provider' => $k->provider, 'is_active' => $k->is_active])
        );
    }

    public function aiKeysStore(Request $request): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $data = $request->validate([
            // `elevenlabs` accepted alongside the LLM providers so a
            // workspace can register its TTS key in the same modal.
            // The AsrDriver/TtsDriver classes look the value up via
            // AiProviderKey::keyFor(workspace, 'elevenlabs').
            'provider' => 'required|in:openai,anthropic,gemini,elevenlabs',
            'api_key'  => 'required|string|min:8|max:512',
        ]);
        $wsId = $request->user()->current_workspace_id;
        $key  = AiProviderKey::updateOrCreate(
            ['workspace_id' => $wsId, 'provider' => $data['provider']],
            ['api_key' => $data['api_key'], 'is_active' => true],
        );
        return response()->json(['ok' => true, 'id' => $key->id, 'provider' => $key->provider]);
    }

    public function aiKeysToggle(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $key = AiProviderKey::where('workspace_id', $request->user()->current_workspace_id)->findOrFail($id);
        $key->update(['is_active' => !$key->is_active]);
        return response()->json(['ok' => true, 'is_active' => $key->is_active]);
    }

    public function aiKeysDestroy(Request $request, int $id): JsonResponse
    {
        if (!WorkspacePermissions::userCan($request->user(), 'integration.manage')) abort(403);
        $key = AiProviderKey::where('workspace_id', $request->user()->current_workspace_id)->findOrFail($id);
        $key->delete();
        return response()->json(['ok' => true]);
    }

    // ----------------------------------------------------------------
    // Assign AI agent to a conversation
    // ----------------------------------------------------------------

    public function assignAgent(Request $request, int $id): JsonResponse
    {
        $data  = $request->validate(['agent_id' => 'nullable|integer']);
        // Attaching an AI agent makes it auto-reply to the customer — gate it
        // behind the same 'assign' permission as human assignment (was missing).
        $convo = $this->findConvInCurrentWorkspace($id);
        $this->authorize('assign', $convo);

        $agentId = $data['agent_id'] ?? null;
        if ($agentId) {
            AiAgent::forWorkspace($request->user()->current_workspace_id)->findOrFail($agentId);
        }

        $old = $convo->assignee_agent_id;
        if ($agentId) {
            $convo->update(['assignee_agent_id' => $agentId]);
            // Stop any running flow session so the flow doesn't keep replying
            // over the AI agent that just took over (see endActiveFlowSession).
            $this->endActiveFlowSession($convo);
        } else {
            // Unassigning the AI Agent = full AI detach. Also clear any
            // voice-assistant attachment (routing_meta.voice_assistant_id) so
            // BOTH AI triggers stop — same guarantee as the assign-assistant
            // detach path. Without this, removing the agent left a voice
            // assistant still replying on the Baileys inbound path.
            $meta = is_array($convo->routing_meta) ? $convo->routing_meta : [];
            unset($meta['voice_assistant_id'], $meta['voice_assistant_name'], $meta['voice_assistant_at']);
            $convo->forceFill([
                'assignee_agent_id' => null,
                'routing_meta'      => $meta,
            ])->save();
        }

        ConversationEvent::record(
            $convo->id, $convo->workspace_id, $request->user()->id,
            $agentId ? 'agent_assigned' : 'agent_unassigned',
            ['old' => $old, 'new' => $agentId],
        );

        return response()->json([
            'ok'         => true,
            'agent_id'   => $agentId,
            'agent_name' => $agentId ? optional(AiAgent::find($agentId))->name : null,
        ]);
    }

    /**
     * Operator "retry download" for a WABA inbound media that failed to fetch at
     * receive-time (e.g. a voice note received before the audio-download fix).
     * Re-pulls it from Meta using the stored media_id (WhatsApp keeps media ~30
     * days) so the customer does NOT have to resend. Returns the fresh media_url.
     */
    public function retryMedia(Request $request, int $id): JsonResponse
    {
        $wsId = (int) ($request->user()?->current_workspace_id ?? 0);
        if ($wsId <= 0) {
            return response()->json(['ok' => false, 'error' => 'no_workspace'], 403);
        }

        // inbox_messages has NO workspace_id column — scope via the parent
        // conversation (conversation_id → conversations.workspace_id).
        $msg = \App\Models\InboxMessage::query()
            ->where('id', $id)
            ->whereHas('conversation', fn ($q) => $q->where('workspace_id', $wsId))
            ->first();
        if (!$msg) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }
        if ($msg->media_path) {
            return response()->json(['ok' => true, 'media_url' => media_url($msg->media_path)]);
        }

        $mediaId = (string) (($msg->meta['waba_media_id'] ?? '') ?: '');
        if ($mediaId === '') {
            // Only WABA inbound media carries a waba_media_id; nothing to retry.
            return response()->json(['ok' => false, 'error' => 'no_media_id'], 422);
        }

        $path = app(\App\Services\Waba\WabaMediaFetcher::class)->downloadToDisk(
            $wsId,
            $mediaId,
            (string) ($msg->meta['waba_mime_type'] ?? '')
        );
        if (!$path) {
            // Media id may have expired (>30 days) or the token is invalid.
            return response()->json(['ok' => false, 'error' => 'download_failed'], 502);
        }

        $msg->forceFill(['media_path' => $path])->save();

        return response()->json(['ok' => true, 'media_url' => media_url($path)]);
    }
}
