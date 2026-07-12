<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminWelcomeMail;
use App\Models\Message;
use App\Models\Package;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkspacesController extends Controller
{
    public function index(Request $request): View
    {
        $q       = trim((string) $request->query('q', ''));
        $statusF = $request->query('status', 'all');
        $planF   = $request->query('plan_id');
        $sort    = $request->query('sort', 'mrr');

        $query = Workspace::query()->with(['owner']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('slug', 'like', "%{$q}%")
                  ->orWhereHas('owner', function ($u) use ($q) {
                      $u->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                  });
            });
        }
        if ($statusF === 'active')    $query->where('status', true);
        if ($statusF === 'suspended') $query->where('status', false);
        if (is_numeric($planF))       $query->where('plan', (int) $planF);

        $query = match ($sort) {
            'created'  => $query->orderByDesc('created_at'),
            'lastseen' => $query->orderByDesc('last_active_at'),
            default    => $query->orderByDesc('id'),
        };

        $workspaces = $query->paginate(12)->withQueryString();
        $planLookup = Package::query()->get()->keyBy('id');

        // Decorate each row with: plan name, plan tone, MRR, 7d msg volume, health.
        $msgWindow = now()->subDays(7);
        $msgCounts = DB::table('messages')
            ->select('workspace_id', DB::raw('COUNT(*) as c'))
            ->where('direction', 'out')
            ->where('created_at', '>=', $msgWindow)
            ->whereIn('workspace_id', $workspaces->pluck('id'))
            ->groupBy('workspace_id')->pluck('c', 'workspace_id');

        $workspaces->getCollection()->transform(function (Workspace $ws) use ($planLookup, $msgCounts) {
            $package = $ws->plan ? $planLookup->get($ws->plan) : null;
            $msgs    = (int) ($msgCounts[$ws->id] ?? 0);
            $ws->_decorated = [
                'plan_name'  => $package?->pname ?? 'Free',
                'plan_tone'  => $this->planTone($package?->pname),
                'mrr'        => (float) ($package?->chargeableAmount() ?? 0),
                'msgs7d'     => $msgs,
                'health'     => $this->health($msgs, (bool) $ws->status),
            ];
            return $ws;
        });

        return view('admin.workspaces.index', [
            'workspaces' => $workspaces,
            'q'          => $q,
            'statusF'    => $statusF,
            'planF'      => $planF,
            'sort'       => $sort,
            'plans'      => Package::query()->plans()->orderBy('plan_amount')->get(['id', 'pname']),
            'stats'      => $this->stats($planLookup),
        ]);
    }

    public function create(): View
    {
        return view('admin.workspaces.create', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'plans' => Package::query()->plans()->orderBy('plan_amount')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name'] . '-' . Str::random(4));

        // Owner resolution. "existing" mode → owner_user_id is set.
        // "invite" mode → create a fresh user from invite_email + name, send welcome mail.
        $ownerId = $data['owner_user_id'] ?? null;
        $inviteNotice = null;
        if (($request->input('owner_mode') ?? 'existing') === 'invite') {
            $inviteData = $request->validate([
                'invite_email' => 'required|email|max:191|unique:users,email',
                'invite_name'  => 'required|string|max:120',
            ]);
            $plainPassword = Str::random(16);
            $invitedUser = User::create([
                'name'              => $inviteData['invite_name'],
                'email'             => $inviteData['invite_email'],
                'password'          => Hash::make($plainPassword),
                'role'              => 'owner',
                'email_verified_at' => null,
                'force_password_change' => true,
            ]);
            $ownerId = $invitedUser->id;
            $inviteNotice = $this->sendInviteEmail($invitedUser, $plainPassword);
        }

        $ws = Workspace::create([
            'name'                    => $data['name'],
            'slug'                    => $data['slug'],
            'custom_domain'           => $data['custom_domain'] ?? null,
            'owner_user_id'           => $ownerId,
            'plan'                    => $data['plan'] ?? null,
            'industry'                => $data['industry'] ?? null,
            'country'                 => $data['country'] ?? null,
            'timezone'                => $data['timezone'] ?? 'Asia/Kolkata',
            'currency'                => $data['currency'] ?? null,
            'billing_cycle'           => $data['billing_cycle'] ?? 'monthly',
            'cap_monthly_messages'    => $data['cap_monthly_messages'] ?? null,
            'cap_daily_messages'      => $data['cap_daily_messages'] ?? null,
            'cap_devices'             => $data['cap_devices'] ?? null,
            'cap_users'               => $data['cap_users'] ?? null,
            'skip_onboarding_email'   => $request->boolean('skip_onboarding_email'),
            'bill_to_platform_credit' => $request->boolean('bill_to_platform_credit'),
            'pre_seed_sample_data'    => $request->boolean('pre_seed_sample_data'),
            'admin_note'              => $data['admin_note'] ?? null,
            'status'                  => true,
            'last_active_at'          => now(),
        ]);

        $owner = User::find($ownerId);
        if (!$owner->current_workspace_id) {
            $owner->update(['current_workspace_id' => $ws->id]);
        }

        Audit::log('admin.workspace.created', [
            'resource' => $ws,
            'meta'     => [
                'plan'         => $ws->plan,
                'owner_mode'   => $request->input('owner_mode', 'existing'),
                'owner_id'     => $ownerId,
                'custom_domain'=> $ws->custom_domain,
            ],
        ]);

        $flash = 'Workspace created.';
        if ($inviteNotice === null && ($request->input('owner_mode') === 'invite')) {
            $flash .= ' Invite email sent to ' . ($owner?->email ?? 'owner') . '.';
        } elseif ($inviteNotice) {
            $flash .= ' Owner created — invite email skipped: ' . $inviteNotice;
        }
        if ($ws->custom_domain) {
            // Pass along DNS setup info so detail page can show the modal.
            session()->flash('dns_setup_for', $ws->id);
        }

        return redirect()->route('admin.workspaces.detail', $ws->id)->with('success', $flash);
    }

    /** Mirror of UsersController::sendWelcomeEmail — same mail-config safety. */
    private function sendInviteEmail(User $user, string $plainPassword): ?string
    {
        $mailer = config('mail.default');
        $from   = config('mail.from.address');
        if (!$mailer) return 'no mailer configured.';
        if (empty($from) || $from === 'hello@example.com') return 'sender address not set.';
        if ($mailer === 'smtp') {
            $cfg = config('mail.mailers.smtp', []);
            if (empty($cfg['host']) || empty($cfg['port'])) return 'SMTP host/port not set.';
        }
        try {
            $resetToken = Password::broker()->createToken($user);
            $resetUrl   = url(route('password.reset', ['token' => $resetToken, 'email' => $user->email], false));
            Mail::to($user->email)->send(new AdminWelcomeMail(
                user: $user,
                loginUrl: url('/login'),
                resetUrl: $resetUrl,
                plainPassword: $plainPassword,
            ));
            $user->forceFill(['welcome_email_sent_at' => now()])->save();
            return null;
        } catch (\Throwable $e) {
            Log::warning('Workspace invite email failed for user ' . $user->id . ': ' . $e->getMessage());
            $msg = $e->getMessage();
            if (stripos($msg, 'authentication') !== false) return 'SMTP authentication failed.';
            if (stripos($msg, 'connection')     !== false) return 'SMTP connection refused.';
            if (stripos($msg, 'timed out')      !== false) return 'SMTP server timed out.';
            return 'mail transport error.';
        }
    }

    /** Detail page — pulls every related count + decoration we need to render the dashboard. */
    public function detail(string $id): View
    {
        $ws = Workspace::with('owner')->findOrFail($id);
        $package = $ws->plan ? Package::find($ws->plan) : null;

        // 30-day message buckets for the volume chart.
        $from = now()->subDays(30)->startOfDay();
        $to   = now()->endOfDay();

        $daily = DB::table('messages')
            ->select(DB::raw('DATE(created_at) as d'),
                     DB::raw("SUM(CASE WHEN direction='out' THEN 1 ELSE 0 END) as sent"),
                     DB::raw("SUM(CASE WHEN direction='out' AND status IN ('delivered','read') THEN 1 ELSE 0 END) as delivered"))
            ->where('workspace_id', $ws->id)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        $labels = $sentSeries = $delSeries = [];
        for ($c = $from->copy(); $c->lte($to); $c->addDay()) {
            $k = $c->format('Y-m-d');
            $labels[]     = $c->format('M j');
            $sentSeries[] = (int) ($daily[$k]->sent ?? 0);
            $delSeries[]  = (int) ($daily[$k]->delivered ?? 0);
        }

        // Aggregate totals (30d).
        $sent30d      = array_sum($sentSeries);
        $delivered30d = array_sum($delSeries);
        $deliveredPct = $sent30d > 0 ? round($delivered30d / $sent30d * 100, 1) : 0;

        $read30d = DB::table('messages')
            ->where('workspace_id', $ws->id)
            ->where('direction', 'out')->where('status', 'read')
            ->where('created_at', '>=', $from)
            ->count();
        $readPct = $sent30d > 0 ? round($read30d / $sent30d * 100, 1) : 0;

        // Related counts — every block guards against missing tables/columns.
        $counts = [
            'devices'    => $this->safeCount('devices',     'workspace_id', $ws->id),
            'campaigns'  => $this->safeCount('campaigns',   'workspace_id', $ws->id),
            'broadcasts' => $this->safeCount('broadcasts',  'workspace_id', $ws->id),
            'contacts'   => $this->safeCount('contacts',    'workspace_id', $ws->id),
            'users'      => $this->safeCount('users',       'current_workspace_id', $ws->id),
        ];

        // MRR + LTV. LTV = MRR × months since created (rough proxy).
        // Effective price = offer price when set, else plan_amount.
        $mrr = (float) ($package?->chargeableAmount() ?? 0);
        $monthsAlive = max(1, (int) $ws->created_at?->diffInMonths(now()));
        $ltv = $mrr * $monthsAlive;

        // Effective limits — using existing model helper.
        $effLimits = [];
        foreach (\App\Http\Controllers\AdminPagesController::PLAN_LIMIT_COLUMNS as $col) {
            $effLimits[$col] = method_exists($ws, 'effectiveLimit') ? $ws->effectiveLimit($col) : ($package?->{$col} ?? null);
        }

        // Recent paid orders.
        $recentOrders = \App\Models\Order::query()
            ->where('workspace_id', $ws->id)
            ->latest('created_at')->limit(6)->get();

        return view('admin.workspaces.detail', [
            'workspace'    => $ws,
            'package'      => $package,
            'limitColumns' => \App\Http\Controllers\AdminPagesController::PLAN_LIMIT_COLUMNS,
            'effLimits'    => $effLimits,
            'users'        => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'plans'        => Package::query()->plans()->orderBy('plan_amount')->get(),
            'volume'       => ['labels' => $labels, 'sent' => $sentSeries, 'delivered' => $delSeries],
            'stats'        => [
                'sent30d'      => $sent30d,
                'delivered30d' => $delivered30d,
                'deliveredPct' => $deliveredPct,
                'read30d'      => $read30d,
                'readPct'      => $readPct,
                'mrr'          => $mrr,
                'ltv'          => $ltv,
                'health'       => $this->health((int) ($sentSeries ? array_sum($sentSeries) : 0), (bool) $ws->status),
            ],
            'counts'       => $counts,
            'recentOrders' => $recentOrders,
        ]);
    }

    /** Safely count rows in a table by column = value, skipping the query when the table/column doesn't exist. */
    private function safeCount(string $table, string $col, int $value): int
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) return 0;
            if (!\Illuminate\Support\Facades\Schema::hasColumn($table, $col)) return 0;
            return (int) DB::table($table)->where($col, $value)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $ws = Workspace::findOrFail($id);
        $data = $this->validatePayload($request, $ws->id);

        $previousPlan = $ws->plan;
        $previousOwner = $ws->owner_user_id;

        $ws->fill([
            'name'                    => $data['name'],
            'slug'                    => $data['slug'] ?: $ws->slug,
            'custom_domain'           => $data['custom_domain'] ?? null,
            'owner_user_id'           => $data['owner_user_id'] ?? $ws->owner_user_id,
            'plan'                    => $data['plan'] ?? null,
            'industry'                => $data['industry'] ?? null,
            'country'                 => $data['country'] ?? null,
            'timezone'                => $data['timezone'] ?? $ws->timezone,
            'currency'                => $data['currency'] ?? $ws->currency,
            'billing_cycle'           => $data['billing_cycle'] ?? $ws->billing_cycle,
            'cap_monthly_messages'    => $data['cap_monthly_messages'] ?? null,
            'cap_daily_messages'      => $data['cap_daily_messages'] ?? null,
            'cap_devices'             => $data['cap_devices'] ?? null,
            'cap_users'               => $data['cap_users'] ?? null,
            'skip_onboarding_email'   => $request->boolean('skip_onboarding_email'),
            'bill_to_platform_credit' => $request->boolean('bill_to_platform_credit'),
            'pre_seed_sample_data'    => $request->boolean('pre_seed_sample_data'),
            'admin_note'              => $data['admin_note'] ?? null,
        ])->save();

        Audit::log('admin.workspace.updated', [
            'resource' => $ws,
            'meta'     => [
                'plan_changed'  => $previousPlan !== $ws->plan ? ['from' => $previousPlan, 'to' => $ws->plan] : null,
                'owner_changed' => $previousOwner !== $ws->owner_user_id ? ['from' => $previousOwner, 'to' => $ws->owner_user_id] : null,
            ],
        ]);

        return back()->with('success', 'Workspace updated.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $ws = Workspace::findOrFail($id);
        $snapshot = ['id' => $ws->id, 'name' => $ws->name, 'slug' => $ws->slug];
        $ws->delete();
        Audit::log('admin.workspace.trashed', ['meta' => $snapshot]);
        return redirect()->route('admin.workspaces.index')->with('success', 'Workspace moved to trash.');
    }

    public function toggleStatus(string $id): RedirectResponse
    {
        $ws = Workspace::findOrFail($id);
        $ws->status = ! (bool) $ws->status;
        $ws->save();
        Audit::log($ws->status ? 'admin.workspace.reactivated' : 'admin.workspace.suspended', [
            'resource' => $ws,
        ]);
        return back()->with('success', $ws->status ? 'Workspace reactivated.' : 'Workspace suspended.');
    }

    private function validatePayload(Request $request, ?int $wsId = null): array
    {
        $slugRule = ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]*$/i'];
        $slugRule[] = $wsId ? Rule::unique('workspaces', 'slug')->ignore($wsId) : Rule::unique('workspaces', 'slug');

        $domainRule = ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'];
        $domainRule[] = $wsId ? Rule::unique('workspaces', 'custom_domain')->ignore($wsId) : Rule::unique('workspaces', 'custom_domain');

        // owner_user_id is only required when the form is in "existing" mode.
        // The "invite" branch in store() validates invite_email separately.
        $mode = $request->input('owner_mode', 'existing');
        $ownerRule = $mode === 'invite' ? 'nullable|exists:users,id' : 'required|exists:users,id';

        return $request->validate([
            'name'                  => 'required|string|max:120',
            'slug'                  => $slugRule,
            'custom_domain'         => $domainRule,
            'owner_user_id'         => $ownerRule,
            'plan'                  => 'nullable|exists:packages,id',
            'industry'              => 'nullable|string|max:80',
            'country'               => 'nullable|string|max:8',
            'timezone'              => 'nullable|string|max:64',
            'currency'              => 'nullable|string|max:8',
            'billing_cycle'         => ['nullable', Rule::in(['monthly', 'quarterly', 'annual', 'custom', 'trial'])],
            'cap_monthly_messages'  => 'nullable|integer|min:0|max:1000000000',
            'cap_daily_messages'    => 'nullable|integer|min:0|max:100000000',
            'cap_devices'           => 'nullable|integer|min:0|max:10000',
            'cap_users'             => 'nullable|integer|min:0|max:10000',
            'admin_note'            => 'nullable|string|max:2000',
        ]);
    }

    private function planTone(?string $name): array
    {
        $n = strtolower($name ?? '');
        if (str_contains($n, 'enterprise')) return ['bg' => '#D9E5F2', 'text' => '#13478A'];
        if (str_contains($n, 'pro'))        return ['bg' => '#F3E9FF', 'text' => '#5B3D8A'];
        if (str_contains($n, 'growth') || str_contains($n, 'standard')) return ['bg' => '#D7F7E6', 'text' => '#075E54'];
        if (str_contains($n, 'starter') || str_contains($n, 'basic'))   return ['bg' => '#FFF4E0', 'text' => '#7B5A14'];
        if (str_contains($n, 'trial'))      return ['bg' => '#EFEBE0', 'text' => '#6B807C'];
        return ['bg' => '#EFEBE0', 'text' => '#6B807C'];
    }

    private function health(int $msgs7d, bool $active): array
    {
        if (!$active)            return ['label' => 'Suspended', 'tone' => 'accent-coral'];
        if ($msgs7d > 50_000)    return ['label' => 'Good',  'tone' => 'wa-deep'];
        if ($msgs7d > 5_000)     return ['label' => 'Watch', 'tone' => 'accent-amber'];
        return ['label' => 'Risk', 'tone' => 'accent-coral'];
    }

    private function stats($planLookup): array
    {
        $total      = Workspace::query()->count();
        $active     = Workspace::query()->where('status', true)->count();
        $suspended  = Workspace::query()->where('status', false)->count();
        $thisMonth  = Workspace::query()->where('created_at', '>=', now()->startOfMonth())->count();

        // "Trial" = on a free plan or no plan.
        $freeIds = Package::query()->where('free', true)->pluck('id')->all();
        $trial   = $freeIds
            ? Workspace::query()->where(function ($q) use ($freeIds) {
                $q->whereIn('plan', $freeIds)->orWhereNull('plan');
              })->count()
            : Workspace::query()->whereNull('plan')->count();

        // MRR = sum of the effective price (offer price when set) across paid workspaces.
        $mrr = (float) Workspace::query()
            ->join('packages', 'packages.id', '=', 'workspaces.plan')
            ->where('packages.free', false)
            ->where('packages.status', true)
            ->sum(\DB::raw('CASE WHEN packages.offer_price IS NOT NULL AND packages.offer_price > 0 THEN packages.offer_price ELSE packages.plan_amount END'));

        return [
            'total'     => $total,
            'active'    => $active,
            'suspended' => $suspended,
            'trial'     => $trial,
            'thisMonth' => $thisMonth,
            'mrr'       => \App\Support\FormatSettings::symbol() . $this->humanCurrency($mrr),
            'retention' => $total > 0 ? round($active / $total * 100, 1) : 0,
        ];
    }

    private function humanCurrency(float $n): string
    {
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
        if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'k';
        return number_format($n, 0);
    }
}
