<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmailVerificationController;
use App\Mail\AdminWelcomeMail;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use App\Support\Audit;
use App\Support\PlatformPermissions;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $q      = trim((string) $request->query('q', ''));
        $role   = (string) $request->query('role', 'all');
        $wsId   = $request->query('workspace_id');

        $query = User::query()->with('currentWorkspaceRel');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('mobile', 'like', "%{$q}%");
            });
        }
        if ($role !== 'all') {
            $query->where('role', $role);
        }
        if (is_numeric($wsId)) {
            $query->where('current_workspace_id', (int) $wsId);
        }

        $users = $query->orderByDesc('id')->paginate(12)->withQueryString();

        $stats = [
            'total'     => User::query()->count(),
            'active'    => User::query()->whereNull('deleted_at')->count(),
            'admin'     => User::query()->where('role', 'admin')->count(),
            'owners'    => User::query()->where('role', 'owner')->count(),
            'suspended' => User::query()->where('role', 'suspended')->count(),
            'trashed'   => User::onlyTrashed()->count(),
            'thisMonth' => User::query()->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return view('admin.users.index', [
            'users'       => $users,
            'q'           => $q,
            'role'        => $role,
            'wsId'        => $wsId,
            'stats'       => $stats,
            'workspaces'  => Workspace::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'workspaces' => Workspace::query()->orderBy('name')->get(['id', 'name']),
            'roles'      => $this->roleOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUserPayload($request);

        // Role escalation guard on create. A non-Super-Admin can't
        // mint a new admin or owner. Downgrade to 'user' and audit
        // the attempt.
        if (in_array($data['role'], ['admin', 'owner'], true) && !PlatformPermissions::isSuperAdmin($request->user())) {
            Audit::log('admin.user.create_privileged_denied', [
                'result' => 'failure',
                'meta'   => ['attempted_role' => $data['role'], 'email' => $data['email'], 'reason' => 'requires_super_admin'],
            ]);
            $data['role'] = 'user';
            session()->flash('error', 'Creating admin/owner accounts requires Super Admin. User was created with the default role.');
        }

        $plainPassword = $data['password'];
        $user = User::create([
            'name'                  => $data['name'],
            'email'                 => $data['email'],
            'mobile'                => $data['mobile'] ?? null,
            'role'                  => $data['role'],
            'current_workspace_id'  => $data['workspace_id'] ?? null,
            'password'              => Hash::make($plainPassword),
            'address'               => $data['address'] ?? null,
            'city'                  => $data['city'] ?? null,
            'state'                 => $data['state'] ?? null,
            'country'               => $data['country'] ?? null,
            'zip'                   => $data['zip'] ?? null,
            'notes'                 => $data['notes'] ?? null,
            'force_password_change' => (bool) $request->input('force_password_change'),
            // "Active immediately" = skip email verification. Mark as verified now.
            'email_verified_at'     => $request->boolean('active') ? now() : null,
        ]);

        // Welcome email — only when the toggle is on. Returns null on success,
        // a skip-reason string when mail is unconfigured / fails (we still create
        // the user successfully; the admin just sees a notice).
        $mailNotice = null;
        if ($request->boolean('welcome_email')) {
            $mailNotice = $this->sendWelcomeEmail($user, $plainPassword);
        }

        return redirect()->route('admin.users.edit', $user->id)
            ->with('success', $mailNotice
                ? 'User created — welcome email skipped: ' . $mailNotice
                : 'User created.');
    }

    public function edit(string $id): View
    {
        $user = User::withTrashed()->findOrFail($id);
        return view('admin.users.edit', [
            'user'       => $user,
            'workspaces' => Workspace::query()->orderBy('name')->get(['id', 'name']),
            'roles'      => $this->roleOptions(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $data = $this->validateUserPayload($request, $user->id);

        // Role escalation guard. Privileged roles (admin/owner) can only
        // be assigned by a Super Admin — a regular `admin` user can edit
        // profile fields but can't promote anyone (incl. themselves)
        // into admin/owner, nor demote an existing admin/owner. The
        // role field is silently reverted to the prior value and the
        // attempt audited.
        $privileged = ['admin', 'owner'];
        $roleChanging = $data['role'] !== $user->role;
        $touchesPrivileged = in_array($data['role'], $privileged, true) || in_array($user->role, $privileged, true);
        if ($roleChanging && $touchesPrivileged && !PlatformPermissions::isSuperAdmin($request->user())) {
            Audit::log('admin.user.role_change_denied', [
                'resource' => $user,
                'result'   => 'failure',
                'meta'     => ['attempted_role' => $data['role'], 'from' => $user->role, 'reason' => 'requires_super_admin'],
            ]);
            $data['role'] = $user->role;
            session()->flash('error', 'Promoting/demoting admin or owner roles requires Super Admin. Other changes were saved.');
        } elseif ($roleChanging) {
            Audit::log('admin.user.role_changed', [
                'resource' => $user,
                'meta'     => ['from' => $user->role, 'to' => $data['role']],
            ]);
        }

        $user->name   = $data['name'];
        $user->email  = $data['email'];
        $user->mobile = $data['mobile'] ?? null;
        $user->role   = $data['role'];
        $user->current_workspace_id = $data['workspace_id'] ?? null;
        $user->address = $data['address'] ?? null;
        $user->city    = $data['city'] ?? null;
        $user->state   = $data['state'] ?? null;
        $user->country = $data['country'] ?? null;
        $user->zip     = $data['zip'] ?? null;
        $user->notes   = $data['notes'] ?? null;
        $user->force_password_change = (bool) $request->input('force_password_change');

        // "Email verified" toggle. Going ON: stamp now. Going OFF: try to send
        // a verification email — if mail is unconfigured, KEEP them verified
        // (we can't make them re-verify when we can't email them).
        $verifyNotice = null;
        if ($request->boolean('active') && !$user->email_verified_at) {
            $user->email_verified_at = now();
        } elseif (!$request->boolean('active') && $user->email_verified_at) {
            $sendErr = EmailVerificationController::send($user);
            if ($sendErr === null) {
                $user->email_verified_at = null;
                $verifyNotice = 'Verification email sent to ' . $user->email . '.';
            } else {
                // Mail unavailable — leave them verified so they don't get locked out.
                $verifyNotice = 'Kept email verified (cannot send verification: ' . $sendErr . ').';
            }
        }

        $newPlainPassword = null;
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $newPlainPassword = $data['password'];
        }
        $user->save();

        $mailNotice = null;
        if ($request->boolean('welcome_email')) {
            $mailNotice = $this->sendWelcomeEmail($user, $newPlainPassword);
        }

        $msg = 'User updated.';
        if ($verifyNotice) $msg .= ' ' . $verifyNotice;
        if ($mailNotice)   $msg .= ' Welcome email skipped: ' . $mailNotice;
        return back()->with('success', $msg);
    }

    /** Revoke every session for the target user. They'll need to log back in. */
    public function forceLogout(string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $count = DB::table('sessions')->where('user_id', $user->id)->delete();
        Audit::log('admin.user.force_logout', [
            'resource' => $user,
            'meta'     => ['sessions_revoked' => $count, 'email' => $user->email],
        ]);
        return back()->with('success', "Force-logged out — {$count} active " . ($count === 1 ? 'session' : 'sessions') . " revoked.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => "You can't trash your own account."]);
        }
        $user->delete();
        return back()->with('success', 'User moved to trash.');
    }

    /** GET /admin/users/trash — list every soft-deleted user with a
     *  30-day countdown, filtered + searchable + paginated. */
    public function trash(\Illuminate\Http\Request $request): View
    {
        $filter = (string) $request->query('filter', 'all');
        $q      = trim((string) $request->query('q', ''));

        $base = User::onlyTrashed()
            ->with(['currentWorkspace:id,name'])
            ->orderByDesc('deleted_at');
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('name',  'like', "%{$q}%")
                  ->orWhere('email','like', "%{$q}%")
                  ->orWhere('phone','like', "%{$q}%");
            });
        }
        if ($filter === 'recent') {
            $base->where('deleted_at', '>=', now()->subDays(7));
        } elseif ($filter === 'expiring') {
            // Trashed > 23 days ago → < 7 days until auto-delete.
            $base->where('deleted_at', '<=', now()->subDays(23));
        }
        $users = $base->paginate(12)->withQueryString();

        $kpi = [
            'total'    => User::onlyTrashed()->count(),
            'recent'   => User::onlyTrashed()->where('deleted_at', '>=', now()->subDays(7))->count(),
            'expiring' => User::onlyTrashed()->where('deleted_at', '<=', now()->subDays(23))->count(),
        ];

        return view('admin.users.trash', compact('users', 'kpi', 'filter', 'q'));
    }

    /** POST /admin/users/{id}/restore — un-trash a soft-deleted user. */
    public function restore(string $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();
        \App\Support\Audit::log('admin.user.restore', [
            'subject_type' => 'user', 'subject_id' => $user->id,
            'meta' => ['email' => $user->email],
        ]);
        return back()->with('success', 'User "' . ($user->name ?: $user->email) . '" restored.');
    }

    /** DELETE /admin/users/{id}/force — wipe a trashed user permanently. */
    public function forceDelete(string $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $label = $user->name ?: $user->email;
        \App\Support\Audit::log('admin.user.force_delete', [
            'subject_type' => 'user', 'subject_id' => $user->id,
            'meta' => ['email' => $user->email],
        ]);
        $user->forceDelete();
        return back()->with('success', 'User "' . $label . '" permanently deleted.');
    }

    /** POST /admin/users/trash/empty — purge every soft-deleted user past
     *  the 30-day grace window. Returns to /admin/users/trash with a count. */
    public function emptyTrash(): RedirectResponse
    {
        $cutoff = now()->subDays(30);
        $expired = User::onlyTrashed()->where('deleted_at', '<', $cutoff)->get();
        $count = $expired->count();
        foreach ($expired as $u) {
            $u->forceDelete();
        }
        \App\Support\Audit::log('admin.user.empty_trash', ['meta' => ['count' => $count]]);
        return back()->with('success', $count > 0
            ? "Permanently deleted {$count} expired users (older than 30 days)."
            : 'Nothing to empty — no trashed users are past the 30-day grace window.');
    }

    public function toggleStatus(Request $request, string $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        // Suspending an admin/owner is itself a privileged action — a
        // regular admin shouldn't be able to suspend a Super Admin or
        // another admin. Gate it.
        if (in_array($user->role, ['admin', 'owner'], true) && !PlatformPermissions::isSuperAdmin($request->user())) {
            Audit::log('admin.user.suspend_privileged_denied', [
                'resource' => $user,
                'result'   => 'failure',
                'meta'     => ['target_role' => $user->role, 'reason' => 'requires_super_admin'],
            ]);
            return back()->with('error', 'Suspending an admin or owner requires Super Admin.');
        }
        $user->role = $user->role === 'suspended' ? 'user' : 'suspended';
        $user->save();
        Audit::log($user->role === 'suspended' ? 'admin.user.suspended' : 'admin.user.reactivated', [
            'resource' => $user,
        ]);
        return back()->with('success', $user->role === 'suspended' ? 'User suspended.' : 'User reactivated.');
    }

    public function resetPassword(string $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        // Rate-limit so an admin (or session-hijacker) can't fire dozens
        // of reset-link emails to a target — Laravel's password broker
        // already throttles per-email, but this enforces a per-admin cap
        // visible in the audit log. 5 sends per admin per hour.
        $throttleKey = 'admin-reset:' . (auth()->id() ?? 'anon');
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            Audit::log('admin.user.reset_password_throttled', [
                'resource' => $user,
                'result'   => 'failure',
                'meta'     => ['retry_after_seconds' => $seconds],
            ]);
            return back()->with('error', 'Too many password reset emails — try again in ' . ceil($seconds / 60) . ' minute(s).');
        }
        RateLimiter::hit($throttleKey, 3600);

        Password::sendResetLink(['email' => $user->email]);
        Audit::log('admin.user.reset_password_sent', [
            'resource' => $user,
            'meta'     => ['email' => $user->email],
        ]);
        return back()->with('success', 'Password reset link sent to ' . $user->email);
    }

    /**
     * Send the admin-issued welcome email.
     *
     * Returns NULL on success, otherwise a short human-readable reason the
     * email was skipped (e.g. "SMTP not configured"). The caller flashes this
     * into the redirect so the admin knows what happened. Form save is never
     * rolled back — user creation must not depend on mail server health.
     */
    private function sendWelcomeEmail(User $user, ?string $plainPassword): ?string
    {
        $configError = $this->mailConfigError();
        if ($configError !== null) {
            Log::info('Admin welcome email skipped for user ' . $user->id . ': ' . $configError);
            return $configError;
        }

        try {
            // Reset-link token — wrapped because the broker can fail when
            // the password_reset_tokens table hasn't been provisioned yet.
            $resetUrl = null;
            try {
                $token = Password::broker()->createToken($user);
                $resetUrl = url(route('password.reset', ['token' => $token, 'email' => $user->email], false));
            } catch (\Throwable $e) {
                Log::info('Reset token unavailable for welcome email: ' . $e->getMessage());
            }

            Mail::to($user->email)->send(new AdminWelcomeMail(
                user: $user,
                loginUrl: url('/login'),
                resetUrl: $resetUrl,
                plainPassword: $plainPassword,
            ));
            $user->forceFill(['welcome_email_sent_at' => now()])->save();
            return null;
        } catch (\Throwable $e) {
            Log::warning('Admin welcome email failed for user ' . $user->id . ': ' . $e->getMessage());
            // Map common transport errors to friendly messages.
            $msg = $e->getMessage();
            if (stripos($msg, 'authentication') !== false) return 'SMTP authentication failed — check username/password.';
            if (stripos($msg, 'connection')     !== false) return 'SMTP connection refused — check host/port.';
            if (stripos($msg, 'timed out')      !== false) return 'SMTP server timed out.';
            if (stripos($msg, 'unable to read') !== false) return 'SMTP server unreachable.';
            return 'mail transport error (see logs).';
        }
    }

    /**
     * Pre-flight check of mail config. Returns NULL when mail looks usable,
     * otherwise a short reason string. We deliberately allow the `log` and
     * `array` drivers through — they always work and are useful for staging.
     */
    private function mailConfigError(): ?string
    {
        $mailer = config('mail.default');
        if (!$mailer) return 'no mailer configured.';

        $from = config('mail.from.address');
        if (empty($from) || $from === 'hello@example.com') {
            return 'sender address (MAIL_FROM_ADDRESS) not set.';
        }

        // The log / array / sendmail drivers don't need network config.
        if (in_array($mailer, ['log', 'array', 'sendmail'], true)) return null;

        $cfg = config("mail.mailers.{$mailer}", []);
        if ($mailer === 'smtp') {
            if (empty($cfg['host']) || $cfg['host'] === '127.0.0.1' && empty(env('MAIL_HOST'))) {
                return 'SMTP host not set.';
            }
            if (empty($cfg['port'])) return 'SMTP port not set.';
            // Username is optional for some relays — don't enforce it.
        }
        // Mailgun / SES / Postmark / Resend each have their own required keys
        // in config/services.php; if those are unset, sending will throw and
        // we'll catch it. We don't pre-validate every driver here.

        return null;
    }

    /** Shared validation for store + update. Pass the user id to skip the unique email rule for self. */
    private function validateUserPayload(Request $request, ?int $userId = null): array
    {
        $emailRule = ['required', 'email', 'max:191'];
        $emailRule[] = $userId
            ? Rule::unique('users', 'email')->ignore($userId)
            : Rule::unique('users', 'email');

        return $request->validate([
            'name'         => 'required|string|max:120',
            'email'        => $emailRule,
            'mobile'       => 'nullable|string|max:32',
            'role'         => ['required', Rule::in(array_keys($this->roleOptions()))],
            'workspace_id' => 'nullable|exists:workspaces,id',
            'password'     => $userId ? 'nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed',
            // Address block.
            'address'      => 'nullable|string|max:500',
            'city'         => 'nullable|string|max:120',
            'state'        => 'nullable|string|max:60',
            'country'      => 'nullable|string|max:8',
            'zip'          => 'nullable|string|max:24',
            'notes'        => 'nullable|string|max:1000',
        ]);
    }

    private function roleOptions(): array
    {
        return [
            'admin'     => 'Admin',
            'owner'     => 'Owner',
            'user'      => 'User',
            'agent'     => 'Agent',
            'suspended' => 'Suspended',
        ];
    }
}
